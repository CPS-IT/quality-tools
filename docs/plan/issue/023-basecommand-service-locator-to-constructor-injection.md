# Issue 023: Simplified command architecture with DTO-based tool runners

|               |                                                                                                      |
|---------------|------------------------------------------------------------------------------------------------------|
| **Status:**   | In Progress (Build Steps 1-2 complete)                                                               |
| **Priority:** | High                                                                                                 |
| **Effort:**   | High (3-5d)                                                                                          |
| **Impact:**   | High                                                                                                 |
| **ADR:**      | [0005 - Simplified command architecture](../../architecture/0005-simplified-command-architecture.md) |

## Description

Replace the BaseCommand/AbstractToolCommand inheritance hierarchy with a flat
architecture: thin commands, DTO-based communication, and tool runners behind a
common interface. See ADR-0005 for the architectural rationale.

## Current State

```
BaseCommand (500 lines, 7 service-locator dependencies)
  -> AbstractToolCommand (410 lines, template method + hooks)
    -> 10 concrete tool commands
  -> 3 config commands (ConfigInit, ConfigShow, ConfigValidate)
```

Problems:

- Service locator anti-pattern (7 getter methods with `new` fallbacks)
- 900 lines of base class code inherited by every command
- Template method pattern with hook points (pre/post-processing)
- Config commands share a base class despite needing almost none of its services
- ContainerAwareInterface/ContainerAwareTrait add hidden coupling

## Target State

```
ToolRunnerRegistry
  -> RectorRunner implements ToolRunnerInterface
  -> PhpStanRunner implements ToolRunnerInterface
  -> PhpCsFixerRunner implements ToolRunnerInterface
  -> FractorRunner implements ToolRunnerInterface
  -> TypoScriptLintRunner implements ToolRunnerInterface
  -> ComposerNormalizeRunner implements ToolRunnerInterface

Commands (flat, ~30 lines each)
  -> RectorLintCommand, RectorFixCommand, ...
  -> Single dependency: ToolRunnerRegistry

Config commands (unchanged, independent)
  -> ConfigInitCommand, ConfigShowCommand, ConfigValidateCommand
```

## Detailed Design

### DTOs

#### ToolRunRequest

```php
final readonly class ToolRunRequest
{
    /**
     * @param array<string, scalar> $toolOptions
     */
    public function __construct(
        public string $toolName,
        public bool $dryRun,
        public ?string $configOverride = null,
        public ?string $pathOverride = null,
        public array $toolOptions = [],
    ) {}
}
```

Carries user intent only. No resolved paths -- runners own resolution.

#### ToolRunResult

```php
final readonly class ToolRunResult
{
    /**
     * @param list<Message> $messages
     */
    public function __construct(
        public int $exitCode,
        public array $messages = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function hasErrors(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->severity === MessageSeverity::Error) {
                return true;
            }
        }
        return false;
    }
}
```

#### Message

```php
final readonly class Message
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public MessageSeverity $severity,
        public string $text,
        public array $context = [],
    ) {}

    public static function info(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Info, $text, $context);
    }

    public static function warning(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Warning, $text, $context);
    }

    public static function error(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Error, $text, $context);
    }
}
```

#### MessageSeverity

```php
enum MessageSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
```

### OutputCollector

```php
interface OutputCollector
{
    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void;
    public function writeError(string $text): void;
}
```

**StreamingOutputCollector** -- wraps OutputInterface, forwards immediately:

```php
final class StreamingOutputCollector implements OutputCollector
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void
    {
        $this->output->write($text);
    }

    public function writeError(string $text): void
    {
        if ($this->output instanceof ConsoleOutputInterface) {
            $this->output->getErrorOutput()->write($text);
        } else {
            $this->output->write($text);
        }
    }
}
```

**BufferingOutputCollector** -- stores in memory for tests:

```php
final class BufferingOutputCollector implements OutputCollector
{
    /** @var list<array{text: string, severity: MessageSeverity}> */
    private array $collected = [];

    /** @var list<string> */
    private array $errors = [];

    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void
    {
        $this->collected[] = ['text' => $text, 'severity' => $severity];
    }

    public function writeError(string $text): void
    {
        $this->errors[] = $text;
    }

    /** @return list<array{text: string, severity: MessageSeverity}> */
    public function getCollected(): array
    {
        return $this->collected;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

### ToolRunnerInterface

```php
interface ToolRunnerInterface
{
    public function run(ToolRunRequest $request, OutputCollector $collector): ToolRunResult;

    /** @return list<string> */
    public function supportedTools(): array;
}
```

Separation of concerns:

- `OutputCollector` carries live process output (stdout/stderr streaming)
- `ToolRunResult::$messages` carries runner diagnostics (resolved paths, warnings, validation)

### ToolRunnerRegistry

```php
final class ToolRunnerRegistry
{
    /** @var array<string, ToolRunnerInterface> */
    private array $runners = [];

    /** @param iterable<ToolRunnerInterface> $runners */
    public function __construct(iterable $runners)
    {
        foreach ($runners as $runner) {
            foreach ($runner->supportedTools() as $tool) {
                $this->runners[$tool] = $runner;
            }
        }
    }

    public function get(string $toolName): ToolRunnerInterface
    {
        return $this->runners[$toolName]
            ?? throw new \InvalidArgumentException('No runner for tool: ' . $toolName);
    }

    public function has(string $toolName): bool
    {
        return isset($this->runners[$toolName]);
    }
}
```

Populated by DI container via tagged service injection.

### Runners

Each runner composes its dependencies via constructor injection. Common
dependencies: `ProcessExecutor`, `ProjectEnvironment`, `ConfigurationLoaderInterface`.
Tool-specific dependencies only where needed.

#### RectorRunner (template for simple runners)

```php
final class RectorRunner implements ToolRunnerInterface
{
    public function __construct(
        private readonly ProcessExecutor $processExecutor,
        private readonly ProjectEnvironment $projectEnv,
        private readonly ConfigurationLoaderInterface $configLoader,
    ) {}

    public function supportedTools(): array
    {
        return ['rector'];
    }

    public function run(ToolRunRequest $request, OutputCollector $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $vendorBinPath = $this->projectEnv->getVendorBinPath();
        $configPath = $this->resolveConfigPath($request);

        $command = [
            $vendorBinPath . '/rector',
            'process',
            '--config=' . $configPath,
        ];

        if ($request->dryRun) {
            $command[] = '--dry-run';
        }

        $targetPaths = $this->resolveTargetPaths($request);
        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        $exitCode = $this->processExecutor->executeWithCollector(
            $command,
            $projectRoot,
            $_SERVER,
            $collector,
        );

        return new ToolRunResult($exitCode);
    }

    private function resolveConfigPath(ToolRunRequest $request): string
    {
        if ($request->configOverride !== null) {
            return $request->configOverride;
        }
        return $this->configLoader->resolveToolConfigPath(
            $request->toolName,
            'rector.php',
            $this->projectEnv->getVendorPath(),
        );
    }

    private function resolveTargetPaths(ToolRunRequest $request): array
    {
        if ($request->pathOverride !== null) {
            return [$request->pathOverride];
        }
        $configuration = $this->configLoader->load($this->projectEnv->getProjectRoot());
        $paths = $configuration->getResolvedPathsForTool('rector');
        return !empty($paths) ? $paths : [$this->projectEnv->getProjectRoot()];
    }
}
```

#### Runner-specific behavior

| Runner                    | Extra dependencies  | Key behavior                                                                           |
|---------------------------|---------------------|----------------------------------------------------------------------------------------|
| `RectorRunner`            | --                  | Simple command + paths                                                                 |
| `PhpCsFixerRunner`        | --                  | Conditional `--using-cache=yes` flag                                                   |
| `TypoScriptLintRunner`    | --                  | Falls back to config-based path discovery                                              |
| `FractorRunner`           | `YamlValidator`     | Pre-validation, post-run summary in messages                                           |
| `PhpStanRunner`           | `FilesystemService` | Temporary neon config for multi-path, `--memory-limit` from toolOptions                |
| `ComposerNormalizeRunner` | `FilesystemService` | Resolves composer executable, iterates over composer.json files, aggregates exit codes |

### Command example

```php
#[AsCommand(name: 'lint:rector', description: '...')]
final class RectorLintCommand extends Command
{
    public function __construct(
        private readonly ToolRunnerRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Override configuration file path')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Target path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = new ToolRunRequest(
            toolName: 'rector',
            dryRun: true,
            configOverride: $input->getOption('config'),
            pathOverride: $input->getOption('path'),
        );

        $collector = new StreamingOutputCollector($output);
        $result = $this->registry->get('rector')->run($request, $collector);

        $this->renderMessages($result, $output);

        return $result->exitCode;
    }

    private function renderMessages(ToolRunResult $result, OutputInterface $output): void
    {
        foreach ($result->messages as $message) {
            match ($message->severity) {
                MessageSeverity::Error => $output->writeln('<error>' . $message->text . '</error>'),
                MessageSeverity::Warning => $output->writeln('<comment>' . $message->text . '</comment>'),
                MessageSeverity::Info => $output->writeln('<info>' . $message->text . '</info>'),
            };
        }
    }
}
```

### ProjectEnvironment

New service replacing project root / vendor detection from BaseCommand and
QualityToolsApplication.

```php
final class ProjectEnvironment
{
    public function __construct(
        private readonly FilesystemService $filesystemService,
        private readonly VendorDirectoryDetector $vendorDetector,
    ) {}

    public function getProjectRoot(): string { ... }
    public function getVendorBinPath(): string { ... }
    public function getVendorPath(): string { ... }
}
```

Should support any Composer project, not just TYPO3.

### ProcessExecutor adaptation

Add a second method during the transition:

```php
// Existing (kept until cleanup phase)
public function executeProcess(
    array $command,
    string $workingDirectory,
    array $environment,
    OutputInterface $output,
): int

// New (used by runners)
public function executeWithCollector(
    array $command,
    string $workingDirectory,
    array $environment,
    OutputCollector $collector,
): int
```

Old method removed and new method renamed to `executeProcess` in cleanup phase.

## Migration Strategy

The entire new infrastructure is built alongside existing code without touching
any existing class or test. Old and new coexist until all commands are migrated.

### Build phase (no existing code touched)

#### Step 1: DTOs, interface, and collector

- [x] Create `src/Messaging/` and `src/ToolRunner/` directories
- [x] Implement `MessageSeverity`, `Message` in `src/Messaging/`
- [x] Implement `ToolRunRequest`, `ToolRunResult` in `src/ToolRunner/`
- [x] Implement `ToolRunnerInterface` in `src/ToolRunner/`
- [x] Implement `OutputCollector` interface in `src/Messaging/`
- [x] Implement `StreamingOutputCollector` and `BufferingOutputCollector` in `src/Messaging/`
- [x] Implement `ToolRunnerRegistry` in `src/ToolRunner/`
- [x] Unit tests for all DTOs, collector implementations, and registry

#### Step 2: ProjectEnvironment

- [x] Create `Service/ProjectEnvironment` (new class)
- [x] Replicate project root detection from QualityToolsApplication
- [x] Replicate vendor path detection from BaseCommand
- [x] Relax TYPO3-only project detection to support any Composer project
- [x] Unit tests
- [ ] Wire into DI container (deferred to migration phase)

#### Step 3: Add executeWithCollector to ProcessExecutor

- [ ] Add `executeWithCollector(array, string, array, OutputCollector): int`
- [ ] New method uses `$collector->write()` / `$collector->writeError()`
- [ ] Old `executeProcess()` stays untouched
- [ ] Tests for the new method

#### Step 4: Runners

Implement all runners independently testable against the new infrastructure.

- [ ] `RectorRunner` -- simplest, template for others
- [ ] `PhpCsFixerRunner` -- conditional parallel processing flag
- [ ] `TypoScriptLintRunner` -- minimal
- [ ] `FractorRunner` -- YAML pre-validation, absorbs FractorCommandTrait logic
- [ ] `PhpStanRunner` -- temporary config file, memory limit from toolOptions
- [ ] `ComposerNormalizeRunner` -- multi-file iteration, executable resolution

Each runner gets unit tests with mock ProcessExecutor and BufferingOutputCollector.

At this point: full new stack built and tested, zero changes to existing code.

### Migration phase (one command at a time)

Migrate one command pair (lint + fix) at a time. After each pair, all tests pass.
Unmigrated commands continue to work on the old hierarchy.

Order: Rector -> PhpCsFixer -> TypoScript -> Fractor -> PHPStan -> Composer

For each command:

- [ ] Rewrite to extend `Command` directly (drop BaseCommand/AbstractToolCommand)
- [ ] Inject `ToolRunnerRegistry` as sole dependency
- [ ] Build `ToolRunRequest` from input, call runner, render result
- [ ] Update/rewrite command tests
- [ ] Verify integration tests pass

### Cleanup phase (after all commands migrated)

- [ ] Delete `BaseCommand`, `AbstractToolCommand`, `FractorCommandTrait`
- [ ] Delete `ContainerAwareInterface`, `ContainerAwareTrait`
- [ ] Delete `CommandBuilder`, `ProcessEnvironmentPreparer`
- [ ] Delete `ToolCommandInterface`, `ErrorHandler`
- [ ] Remove `executeProcess()` from ProcessExecutor, rename `executeWithCollector` to `executeProcess`
- [ ] Update `services.yaml` (remove old wiring, finalize runner registrations)
- [ ] Verify full test suite

## Files Created

| File                                          | Phase   |
|-----------------------------------------------|---------|
| `src/Messaging/MessageSeverity.php`           | Build 1 |
| `src/Messaging/Message.php`                   | Build 1 |
| `src/Messaging/OutputCollector.php`           | Build 1 |
| `src/Messaging/StreamingOutputCollector.php`  | Build 1 |
| `src/Messaging/BufferingOutputCollector.php`  | Build 1 |
| `src/ToolRunner/ToolRunRequest.php`           | Build 1 |
| `src/ToolRunner/ToolRunResult.php`            | Build 1 |
| `src/ToolRunner/ToolRunnerInterface.php`      | Build 1 |
| `src/ToolRunner/ToolRunnerRegistry.php`       | Build 1 |
| `src/Service/ProjectEnvironment.php`          | Build 2 |
| `src/ToolRunner/RectorRunner.php`             | Build 4 |
| `src/ToolRunner/PhpCsFixerRunner.php`         | Build 4 |
| `src/ToolRunner/TypoScriptLintRunner.php`     | Build 4 |
| `src/ToolRunner/FractorRunner.php`            | Build 4 |
| `src/ToolRunner/PhpStanRunner.php`            | Build 4 |
| `src/ToolRunner/ComposerNormalizeRunner.php`  | Build 4 |

## Files Deleted (Cleanup Phase)

- `src/Console/Command/BaseCommand.php`
- `src/Console/Command/AbstractToolCommand.php`
- `src/Console/Command/FractorCommandTrait.php`
- `src/Console/Command/ToolCommandInterface.php`
- `src/DependencyInjection/ContainerAwareInterface.php`
- `src/DependencyInjection/ContainerAwareTrait.php`
- `src/Service/CommandBuilder.php`
- `src/Service/ProcessEnvironmentPreparer.php`
- `src/Service/ErrorHandler.php`

## Validation Plan

- [x] All existing tests pass after each build step (no existing code changed)
- [x] Each DTO, collector, and runner has unit tests (Step 1 DTOs and collectors done)
- [ ] All existing tests pass after each command migration
- [ ] No service locator calls remain after cleanup
- [ ] ContainerAwareInterface / ContainerAwareTrait deleted
- [x] PHPStan level 6 clean (Step 1 code verified)
- [ ] No behavioral changes from the user perspective
- [ ] Commands have exactly one constructor dependency (ToolRunnerRegistry)

## Open Questions

**Memory optimization**: Drop MemoryCalculator/ProjectAnalyzer entirely. Expose
`--memory-limit` as a direct command option for PHPStan (via `toolOptions`).

**TYPO3 project detection**: Relax in ProjectEnvironment to support any Composer
project, enabling `qt` to lint itself.

## Dependencies

- Issue 019 (Configuration Class Hierarchy Simplification) should be complete
  before starting, to avoid conflicting changes in BaseCommand
- Issue 020 (DI Configuration Inconsistency) resolved as a side effect of this work

## Related Issues

- [019 - Configuration Class Hierarchy Simplification](019-configuration-class-hierarchy-simplification.md)
- [020 - DI Configuration Inconsistency](020-di-configuration-inconsistency.md)
- [013 - Dependency Injection Container Architecture](done/013-dependency-injection-container-architecture.md)
- [018 - BaseCommand ExecuteProcess Method Refactoring](done/018-basecommand-executeprocess-method-refactoring.md)
