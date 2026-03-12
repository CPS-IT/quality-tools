# Issue 023: Simplified command architecture with DTO-based tool runners

|               |                                                                                                      |
|---------------|------------------------------------------------------------------------------------------------------|
| **Status:**   | Done                                                                                                 |
| **Priority:** | High                                                                                                 |
| **Effort:**   | High (3-5d)                                                                                          |
| **Impact:**   | High                                                                                                 |
| **ADR:**      | [0005 - Simplified command architecture](../../../architecture/0005-simplified-command-architecture.md) |

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

Commands (parameterized, ~90 lines each, registered via DI tags)
  -> RectorCommand (registered as lint:rector and fix:rector)
  -> PhpCsFixerCommand (registered as lint:php-cs-fixer and fix:php-cs-fixer)
  -> Dependencies: ToolRunnerRegistry, ToolRunInfoDisplay
  -> Future: same pattern for Fractor, TypoScript, PHPStan, Composer

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

### OutputCollectorInterface

```php
interface OutputCollectorInterface
{
    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void;
    public function writeError(string $text): void;
}
```

**StreamingOutputCollector** -- wraps OutputInterface, forwards immediately:

```php
final class StreamingOutputCollector implements OutputCollectorInterface
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
final class BufferingOutputCollector implements OutputCollectorInterface
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
    public function describe(ToolRunRequest $request): ToolRunDescription;
    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult;
    /** @return list<string> */
    public function supportedTools(): array;
}
```

Separation of concerns:

- `OutputCollectorInterface` carries live process output (stdout/stderr streaming)
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
        private readonly ?MemoryOptimizer $memoryOptimizer = null,
    ) {}

    public function supportedTools(): array
    {
        return ['rector'];
    }

    public function describe(ToolRunRequest $request): ToolRunDescription
    {
        $configPath = $this->resolveConfigPath($request);
        $targetPaths = $this->resolveTargetPaths($request);
        $memoryLimit = $this->memoryOptimizer?->calculateMemoryLimit('rector', $targetPaths);

        return new ToolRunDescription(
            configPath: $configPath,
            targetPaths: $targetPaths,
            memoryLimit: $memoryLimit,
        );
    }

    public function run(ToolRunRequest $request, OutputCollectorInterface $collector): ToolRunResult
    {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $vendorBinPath = $this->projectEnv->getVendorBinPath();
        $description = $this->describe($request);

        $command = [
            $vendorBinPath . '/rector',
            'process',
            '--config=' . $description->configPath,
        ];

        if ($request->dryRun) {
            $command[] = '--dry-run';
        }

        if ($description->memoryLimit !== null) {
            $command[] = '-d';
            $command[] = 'memory_limit=' . $description->memoryLimit;
        }

        foreach ($description->targetPaths as $path) {
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
final class RectorCommand extends Command
{
    public function __construct(
        private readonly ToolRunnerRegistry $registry,
        private readonly ToolRunInfoDisplay $infoDisplay,
        private readonly bool $dryRun,
        string $name,
        string $description,
        string $help,
    ) {
        parent::__construct($name);
        $this->setDescription($description);
        $this->setHelp($help);
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
            dryRun: $this->dryRun,
            configOverride: $input->getOption('config'),
            pathOverride: $input->getOption('path'),
        );

        $runner = $this->registry->get('rector');
        $description = $runner->describe($request);
        $this->infoDisplay->render($description, $output);

        $collector = new StreamingOutputCollector($output);
        $result = $runner->run($request, $collector);

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

Two DI registrations cover both command names. `RectorCommand.lint` is wired with
`dryRun: true` and registered as `lint:rector`. `RectorCommand.fix` is wired with
`dryRun: false` and registered as `fix:rector`. No `#[AsCommand]` attribute is
used because the name is injected at construction time.

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
    OutputCollectorInterface $collector,
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
- [x] Implement `OutputCollectorInterface` interface in `src/Messaging/`
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

- [x] Add `executeWithCollector(array, string, array, OutputCollectorInterface): int`
- [x] New method uses `$collector->write()` / `$collector->writeError()`
- [x] Old `executeProcess()` stays untouched
- [x] Tests for the new method

#### Step 4: Runners and ToolName enum

Implement all runners independently testable against the new infrastructure.

- [x] `ToolName` backed enum as single source of truth for tool identifiers
- [x] `RectorRunner` -- simplest, template for others
- [x] `PhpCsFixerRunner` -- conditional cache flag, dry-run with --diff
- [x] `TypoScriptLintRunner` -- minimal, uses -c flag
- [x] `FractorRunner` -- QT_DYNAMIC_PATHS env var, project root fallback
- [x] `PhpStanRunner` -- temporary neon config for multi-path, --memory-limit and --level from toolOptions
- [x] `ComposerNormalizeRunner` -- multi-file iteration, executable resolution with phar validation

Each runner gets unit tests with mock Process via factory closure and BufferingOutputCollector.
Namespace migrated from `Cpsit\QualityTools\ToolRunner` to `Cpsit\QualityTools\Tool\Runner`.
ToolName enum placed in `Cpsit\QualityTools\Tool` namespace.

At this point: full new stack built and tested, zero changes to existing code.

#### Step 5: MemoryOptimizer service

Add memory optimization support to runners that need it. The existing
`MemoryCalculator` and `ProjectAnalyzer` are standalone utilities -- compose them
into a new service injected into runners.

- [x] Create `Service/MemoryOptimizer` composing `ProjectAnalyzer` + `MemoryCalculator`
- [x] Method: `calculateMemoryLimit(string $toolName, list<string> $targetPaths): string`
- [x] Inject into runners that need memory optimization: Rector (1.5x), PhpStan (1.2x), PhpCsFixer (1.0x), Fractor (0.8x)
- [x] TypoScriptLintRunner and ComposerNormalizeRunner do not need memory optimization
- [x] PhpStanRunner: auto-calculate when toolOptions['memory-limit'] not explicitly set
- [x] Other runners: inject memory limit as PHP `-d memory_limit=` flag in command
- [x] Unit tests for MemoryOptimizer
- [x] Runner tests for memory limit integration

### Migration phase (one command at a time)

Migrate one command pair (lint + fix) at a time. After each pair, all tests pass.
Unmigrated commands continue to work on the old hierarchy.

Order: Rector -> PhpCsFixer -> TypoScript -> Fractor -> PHPStan -> Composer

#### Rector (complete)

- [x] Consolidated RectorLintCommand + RectorFixCommand into parameterized RectorCommand
- [x] Two DI registrations: RectorCommand.lint (dryRun: true) and RectorCommand.fix (dryRun: false)
- [x] Added describe() to ToolRunnerInterface, implemented in all 6 runners
- [x] Created ToolRunDescription DTO for pre-run info
- [x] Created ToolRunInfoDisplay for rendering optimization details
- [x] Added resolveToolConfigPath() to ConfigurationLoaderInterface for config auto-discovery
- [x] Updated RectorRunner to use config auto-discovery before package defaults
- [x] Made MemoryOptimizer::analyzeAndAggregate() public for describe() metrics
- [x] Fixed CustomToolConfigurationTest rector scenarios (VendorDirectoryDetector cache, PHP mock executable)
- [x] Updated QualityToolsApplication with registerRunnerCommands() for suffixed service IDs
- [x] All 1165 tests pass, 0 CS Fixer issues, 0 PHPStan errors

#### PhpCsFixer (complete)

- [x] Consolidated PhpCsFixerLintCommand + PhpCsFixerFixCommand into parameterized PhpCsFixerCommand
- [x] Two DI registrations: PhpCsFixerCommand.lint (dryRun: true) and PhpCsFixerCommand.fix (dryRun: false)
- [x] Registered PhpCsFixerRunner in ToolRunnerRegistry
- [x] Added config auto-discovery to PhpCsFixerRunner via resolveToolConfigPath()
- [x] Converted mock php-cs-fixer executable from bash to PHP for MemoryOptimizer compatibility
- [x] All 1170 tests pass, 0 CS Fixer issues, 0 PHPStan errors

#### DI-tagged command registration (complete)

- [x] Added registerForAutoconfiguration(Command::class)->addTag('console.command') in ServiceContainer
- [x] Replaced file-scanning registerCommands() + hardcoded registerRunnerCommands() in QualityToolsApplication
- [x] Single registerCommands() method using findTaggedServiceIds('console.command')
- [x] Suffixed service IDs (e.g. RectorCommand.lint) get explicit console.command tags in services.yaml

#### Note on command duplication

RectorCommand and PhpCsFixerCommand are structurally identical. The only
differences are the ToolName arguments in the execute() method (lines ~77 and
~83). A ToolCommandTrait or a generic ToolCommand class could eliminate this
duplication. This will be discussed after all commands are migrated and the
full pattern is visible.

#### Fractor (complete)

- [x] Consolidated FractorLintCommand + FractorFixCommand into parameterized FractorCommand
- [x] Two DI registrations: FractorCommand.lint (dryRun: true) and FractorCommand.fix (dryRun: false)
- [x] Fractor-specific YAML pre-validation moved from FractorCommandTrait into FractorCommand
- [x] Added config auto-discovery to FractorRunner via resolveToolConfigPath()
- [x] Converted mock fractor executable from bash to PHP for MemoryOptimizer compatibility
- [x] Deleted FractorCommandTrait (no longer used)
- [x] All 1175 tests pass, 0 CS Fixer issues, 0 PHPStan errors

#### TypoScriptLint (complete)

- [x] Rewrote TypoScriptLintCommand to use runner infrastructure (no AbstractToolCommand)
- [x] Single DI registration (lint-only tool, no fix mode, no dryRun parameter)
- [x] Added config auto-discovery to TypoScriptLintRunner via resolveToolConfigPath()
- [x] Added TypoScriptLintRunner to ToolRunnerRegistry
- [x] No --no-optimization option (no MemoryOptimizer for this tool)
- [x] All 1174 tests pass, 0 CS Fixer issues, 0 PHPStan errors

#### ComposerNormalize (complete)

- [x] Created ComposerNormalizeCommand with parameterized lint/fix modes
- [x] Two DI registrations: ComposerNormalizeCommand.lint (dryRun: true) and ComposerNormalizeCommand.fix (dryRun: false)
- [x] Only --path option (no --config since composer-normalize uses no config file)
- [x] Renders runner messages (e.g. "no composer.json files found" warning)
- [x] Added ComposerNormalizeRunner to ToolRunnerRegistry
- [x] All tests pass, 0 CS Fixer issues, 0 PHPStan errors

#### PhpStan (complete)

- [x] Rewrote PhpStanCommand to use runner infrastructure
- [x] Single DI registration (lint-only tool, no fix mode)
- [x] Extra options: --level, --memory-limit, --no-optimization
- [x] Passes level and memory-limit as toolOptions to ToolRunRequest
- [x] Added config auto-discovery to PhpStanRunner via resolveToolConfigPath()
- [x] Updated PhpStanTempFileCleanupTest integration test for new constructor
- [x] All tests pass, 0 CS Fixer issues, 0 PHPStan errors

### Cleanup phase (in progress)

Note: Config commands (ConfigInit, ConfigShow, ConfigValidate) still depend
on BaseCommand. These will be addressed separately after the first cleanup round.

- [ ] Delete `AbstractToolCommand` and its tests (no production code extends it)
- [ ] Delete `CommandBuilder`, `ProcessEnvironmentPreparer` and their tests
- [ ] Delete `ContainerAwareInterface`, `ContainerAwareTrait`
- [ ] Delete `ToolCommandInterface`
- [ ] Delete old command test files (RectorLintCommandTest, RectorFixCommandTest, etc.)
- [ ] Remove `executeProcess()` from ProcessExecutor, rename `executeWithCollector` to `executeProcess`
- [ ] Update `services.yaml` (remove old wiring, finalize runner registrations)
- [ ] Verify full test suite
- [ ] Later: migrate Config commands off BaseCommand, then delete BaseCommand and ErrorHandler

## Files Created

| File                                              | Phase   |
|---------------------------------------------------|---------|
| `src/Messaging/MessageSeverity.php`               | Build 1 |
| `src/Messaging/Message.php`                       | Build 1 |
| `src/Messaging/OutputCollectorInterface.php`      | Build 1 |
| `src/Messaging/StreamingOutputCollectorInterface.php`  | Build 1 |
| `src/Messaging/BufferingOutputCollectorInterface.php`  | Build 1 |
| `src/Tool/Runner/ToolRunRequest.php`              | Build 1 |
| `src/Tool/Runner/ToolRunResult.php`               | Build 1 |
| `src/Tool/Runner/ToolRunnerInterface.php`         | Build 1 |
| `src/Tool/Runner/ToolRunnerRegistry.php`          | Build 1 |
| `src/Service/ProjectEnvironment.php`              | Build 2 |
| `src/Tool/ToolName.php`                           | Build 4 |
| `src/Tool/Runner/RectorRunner.php`                | Build 4 |
| `src/Tool/Runner/PhpCsFixerRunner.php`            | Build 4 |
| `src/Tool/Runner/TypoScriptLintRunner.php`        | Build 4 |
| `src/Tool/Runner/FractorRunner.php`               | Build 4 |
| `src/Tool/Runner/PhpStanRunner.php`               | Build 4 |
| `src/Tool/Runner/ComposerNormalizeRunner.php`     | Build 4 |
| `src/Tool/Runner/ToolRunDescription.php`          | Migration |
| `src/Console/Output/ToolRunInfoDisplay.php`       | Migration |
| `src/Console/Command/RectorCommand.php`           | Migration |
| `src/Console/Command/PhpCsFixerCommand.php`       | Migration |
| `src/Console/Command/FractorCommand.php`          | Migration |
| `src/Console/Command/TypoScriptLintCommand.php`   | Migration (rewritten) |
| `src/Console/Command/PhpStanCommand.php`          | Migration (rewritten) |
| `src/Console/Command/ComposerNormalizeCommand.php` | Migration |

## Files Deleted

### Migration Phase (already deleted)

- `src/Console/Command/RectorLintCommand.php` (replaced by parameterized RectorCommand)
- `src/Console/Command/RectorFixCommand.php` (replaced by parameterized RectorCommand)
- `src/Console/Command/PhpCsFixerLintCommand.php` (replaced by parameterized PhpCsFixerCommand)
- `src/Console/Command/PhpCsFixerFixCommand.php` (replaced by parameterized PhpCsFixerCommand)
- `src/Console/Command/FractorLintCommand.php` (replaced by parameterized FractorCommand)
- `src/Console/Command/FractorFixCommand.php` (replaced by parameterized FractorCommand)
- `src/Console/Command/FractorCommandTrait.php` (YAML validation moved into FractorCommand)

### Cleanup Phase

- `src/Console/Command/BaseCommand.php`
- `src/Console/Command/AbstractToolCommand.php`
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
- [ ] Commands have two constructor dependencies (ToolRunnerRegistry, ToolRunInfoDisplay)

## Open Questions

**Memory optimization**: Resolved. MemoryCalculator and ProjectAnalyzer are
composed into MemoryOptimizer, which is injected as an optional dependency into
runners that need it. The describe() method calls
MemoryOptimizer::analyzeAndAggregate() (made public for this purpose) to produce
memory metrics for pre-run display. Runners pass the calculated limit as a PHP
`-d memory_limit=` flag in the command array.

**TYPO3 project detection**: Relax in ProjectEnvironment to support any Composer
project, enabling `qt` to lint itself.

## Dependencies

- Issue 019 (Configuration Class Hierarchy Simplification) should be complete
  before starting, to avoid conflicting changes in BaseCommand
- Issue 020 (DI Configuration Inconsistency) resolved as a side effect of this work

## Related Issues

- [019 - Configuration Class Hierarchy Simplification](019-configuration-class-hierarchy-simplification.md)
- [020 - DI Configuration Inconsistency](020-di-configuration-inconsistency.md)
- [013 - Dependency Injection Container Architecture](013-dependency-injection-container-architecture.md)
- [018 - BaseCommand ExecuteProcess Method Refactoring](018-basecommand-executeprocess-method-refactoring.md)
