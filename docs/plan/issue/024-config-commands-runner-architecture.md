# Issue 024: Migrate Config commands to runner architecture

|               |                                                     |
|---------------|-----------------------------------------------------|
| **Status:**   | In Progress                                         |
| **Priority:** | High                                                |
| **Effort:**   | Medium (2-3d)                                       |
| **Impact:**   | High                                                |
| **Depends:**  | Issue 023 (tool command migration, cleanup phase)    |

## Description

Migrate the three Config commands (ConfigInit, ConfigShow, ConfigValidate) off
BaseCommand into a dedicated command runner architecture. Config operations are
internal -- they do not invoke external tools. They belong in their own domain
(`Console\Runner`) with dedicated request DTOs, not squeezed into the
`Tool\Runner` namespace.

This eliminates BaseCommand and its entire dependency chain:

- BaseCommand (500 lines, service-locator pattern)
- ContainerAwareInterface / ContainerAwareTrait
- CommandBuilder
- ProcessEnvironmentPreparer
- ProcessExecutor::executeProcess() (old method)

## Current State

```
BaseCommand (500 lines, ContainerAwareInterface, 7 service-locator getters)
  -> ConfigInitCommand   (uses: getProjectRoot(), configurationLoader)
  -> ConfigShowCommand   (uses: getProjectRoot(), configurationLoader)
  -> ConfigValidateCommand (uses: getProjectRoot(), configurationLoader)
```

Problems:

- Config commands inherit 500 lines of code they do not use
- ContainerAwareInterface couples commands to the DI container at runtime
- ConfigInitCommand embeds ~200 lines of template generation inline
- ConfigShowCommand instantiates SecurityService and ConfigurationHierarchy directly
- ConfigValidateCommand has inline config_file path validation logic
- None of the three use process execution, memory optimization, or vendor detection

## Target State

```
Console\Runner (new domain, parallel to Tool\Runner):
  CommandRunnerInterface
    -> ConfigInitRunner     orchestrates ConfigurationTemplateGenerator + FilesystemService
    -> ConfigShowRunner     orchestrates ConfigurationLoader + ConfigurationHierarchy
    -> ConfigValidateRunner orchestrates ConfigurationLoader + config_file validation

Request DTOs (one per operation, only the fields each needs):
  -> ConfigInitRequest      { template, force }
  -> ConfigShowRequest      { format }
  -> ConfigValidateRequest  { (empty) }

Result: reuse ToolRunResult + Message (generic enough)

Config commands (thin shells, extend Command directly):
  -> ConfigInitCommand      unwrap input -> ConfigInitRequest -> runner -> render result
  -> ConfigShowCommand      unwrap input -> ConfigShowRequest -> runner -> render result
  -> ConfigValidateCommand  unwrap input -> ConfigValidateRequest -> runner -> render result

Deleted:
  -> BaseCommand, ContainerAwareInterface, ContainerAwareTrait
  -> CommandBuilder, ProcessEnvironmentPreparer
  -> ProcessExecutor::executeProcess()
  -> All BaseCommand tests
```

## Detailed Design

### Why a separate domain

Config commands do not run external tools. They perform in-process operations:
file generation, configuration loading, schema validation. Forcing them into
`Tool\Runner` with `ToolRunRequest` (dryRun, pathOverride, configOverride) and
`ToolRunnerInterface` creates a semantic mismatch -- those fields are meaningless
for config operations.

A parallel `Console\Runner` namespace with its own `CommandRunnerInterface` keeps
the same structural pattern (describe + run) while allowing typed request DTOs
that match each operation's actual needs.

### CommandRunnerInterface

Mirrors the shape of `ToolRunnerInterface` but uses operation-specific request
types. Since PHP lacks generics, each runner method accepts its own typed request
rather than a shared base.

```php
namespace Cpsit\QualityTools\Console\Runner;

use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Cpsit\QualityTools\Tool\Runner\ToolRunResult;

interface CommandRunnerInterface
{
    /**
     * Describe what the runner will do without executing.
     */
    public function describe(object $request): CommandRunDescription;

    /**
     * Execute the operation and return a structured result.
     */
    public function run(object $request, OutputCollectorInterface $collector): ToolRunResult;
}
```

Note: `object` type hint keeps the interface simple. Each runner internally
asserts the expected request type. An alternative is three separate interfaces
(one per runner) but that adds indirection without benefit since there is no
shared registry for command runners.

**Pragmatic alternative: no shared interface.** Each runner is a standalone
class with `describe()` and `run()` methods typed to its own request. Commands
inject their runner directly -- no registry lookup needed since the mapping is
1:1. This avoids the `object` type hint entirely:

```php
final readonly class ConfigInitRunner
{
    public function describe(ConfigInitRequest $request): CommandRunDescription { ... }
    public function run(ConfigInitRequest $request, OutputCollectorInterface $collector): ToolRunResult { ... }
}
```

**Recommendation:** Start without a shared interface. The three runners follow
the same structural convention (describe + run) but are independently typed.
If a shared interface becomes useful later (e.g. for a registry or decorator),
it can be introduced then.

### Request DTOs

Each operation gets its own request with only the fields it needs.

```php
namespace Cpsit\QualityTools\Console\Runner;

final readonly class ConfigInitRequest
{
    public function __construct(
        public string $template = 'default',
        public bool $force = false,
    ) {
    }
}

final readonly class ConfigShowRequest
{
    public function __construct(
        public string $format = 'yaml',
    ) {
    }
}

final readonly class ConfigValidateRequest
{
    public function __construct() {
    }
}
```

Clean, typed, no unused fields.

### CommandRunDescription

Parallel to `ToolRunDescription` but simpler -- no metrics, no memory limit.

```php
namespace Cpsit\QualityTools\Console\Runner;

final readonly class CommandRunDescription
{
    /**
     * @param array<string, scalar> $info
     */
    public function __construct(
        public string $operationName,
        public string $configPath,
        public array $info = [],
    ) {
    }
}
```

### Result

Reuse `ToolRunResult` and `Message` from the Messaging namespace. They are
generic (exit code + messages) and not tool-specific despite the name. Renaming
them is out of scope but could be done later if desired.

### Runners

#### ConfigInitRunner

Orchestrates template generation and file writing. Template logic is extracted
into `ConfigurationTemplateGenerator`.

```php
namespace Cpsit\QualityTools\Console\Runner;

final readonly class ConfigInitRunner
{
    public function __construct(
        private ConfigurationTemplateGenerator $templateGenerator,
        private ConfigurationLoaderInterface $configLoader,
        private FilesystemService $filesystemService,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function describe(ConfigInitRequest $request): CommandRunDescription
    {
        $projectRoot = $this->projectEnv->getProjectRoot();

        return new CommandRunDescription(
            operationName: 'config-init',
            configPath: $projectRoot . '/.quality-tools.yaml',
            info: ['template' => $request->template],
        );
    }

    public function run(
        ConfigInitRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        $projectRoot = $this->projectEnv->getProjectRoot();
        $configFile = $projectRoot . '/.quality-tools.yaml';

        // Check existing config
        $existing = $this->configLoader->findConfigurationFile($projectRoot);
        if ($existing !== null && !$request->force) {
            return new ToolRunResult(0, [
                Message::warning(sprintf(
                    'Configuration file already exists: %s', $existing
                )),
                Message::info('Use --force to overwrite the existing configuration.'),
            ]);
        }

        // Generate and write
        $content = $this->templateGenerator->generate(
            $request->template, $projectRoot
        );
        $this->filesystemService->writeFile($configFile, $content);

        return new ToolRunResult(0, [
            Message::info(sprintf('Created configuration file: %s', $configFile)),
            Message::info(sprintf('Template used: %s', $request->template)),
        ]);
    }
}
```

#### ConfigurationTemplateGenerator

Extracted from ConfigInitCommand. Owns template content and project name
detection. Independently testable.

```php
namespace Cpsit\QualityTools\Configuration;

final readonly class ConfigurationTemplateGenerator
{
    private const array TEMPLATES = [
        'default' => 'Default Configuration',
        'typo3-extension' => 'TYPO3 Extension',
        'typo3-site-package' => 'TYPO3 Site Package',
        'typo3-distribution' => 'TYPO3 Distribution',
    ];

    public function generate(string $template, string $projectRoot): string
    {
        $projectName = $this->detectProjectName($projectRoot);

        return match ($template) {
            'typo3-extension' => $this->extensionTemplate($projectName),
            'typo3-site-package' => $this->sitePackageTemplate($projectName),
            'typo3-distribution' => $this->distributionTemplate($projectName),
            default => $this->baseTemplate($projectName),
        };
    }

    public function isValidTemplate(string $template): bool
    {
        return array_key_exists($template, self::TEMPLATES);
    }

    /** @return array<string, string> */
    public function getAvailableTemplates(): array
    {
        return self::TEMPLATES;
    }

    private function detectProjectName(string $projectRoot): string { ... }
    private function baseTemplate(string $projectName): string { ... }
    private function extensionTemplate(string $projectName): string { ... }
    private function sitePackageTemplate(string $projectName): string { ... }
    private function distributionTemplate(string $projectName): string { ... }
}
```

#### ConfigShowRunner

Orchestrates configuration loading, source display, and formatting.
No inline validation -- ConfigurationLoader handles YAML parsing and env var
interpolation internally.

```php
namespace Cpsit\QualityTools\Console\Runner;

final readonly class ConfigShowRunner
{
    public function __construct(
        private ConfigurationLoaderInterface $configLoader,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function run(
        ConfigShowRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        // Load configuration (loader handles validation internally)
        $configuration = $this->configLoader->load($projectRoot);
        $configData = $configuration->toArray();

        // Format and write output via collector
        // Build source info as messages (shown when verbose)
        // ...
    }
}
```

#### ConfigValidateRunner

Orchestrates configuration loading and delegates path validation to
`ConfigurationValidator::validateToolConfigFilePaths`.

```php
namespace Cpsit\QualityTools\Console\Runner;

final readonly class ConfigValidateRunner
{
    public function __construct(
        private ConfigurationLoaderInterface $configLoader,
        private ConfigurationValidator $configValidator,
        private ProjectEnvironment $projectEnv,
    ) {
    }

    public function run(
        ConfigValidateRequest $request,
        OutputCollectorInterface $collector,
    ): ToolRunResult {
        // Load and validate (ConfigurationLoader performs schema validation)
        $configuration = $this->configLoader->load($projectRoot);

        // Delegate config_file path checking to ConfigurationValidator
        $warnings = $this->configValidator->validateToolConfigFilePaths(
            $configuration->toArray(), $projectRoot
        );
        // ...
    }
}
```

### Command pattern

Three separate command classes. Each injects its runner directly (no registry
needed -- the mapping is 1:1 and known at compile time).

```php
final class ConfigInitCommand extends Command
{
    public function __construct(
        private readonly ConfigInitRunner $runner,
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
            ->addOption('template', 't', InputOption::VALUE_REQUIRED,
                'Template: default, typo3-extension, typo3-site-package, typo3-distribution',
                'default')
            ->addOption('force', 'f', InputOption::VALUE_NONE,
                'Overwrite existing configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $request = new ConfigInitRequest(
                template: $input->getOption('template'),
                force: (bool) $input->getOption('force'),
            );

            // Validate template before running
            // (runner's templateGenerator handles this)

            $collector = new StreamingOutputCollector($output);
            $result = $this->runner->run($request, $collector);

            // Render messages
            foreach ($result->messages as $message) {
                match ($message->severity) {
                    MessageSeverity::Error => $output->writeln('<error>' . $message->text . '</error>'),
                    MessageSeverity::Warning => $output->writeln('<comment>' . $message->text . '</comment>'),
                    MessageSeverity::Info => $output->writeln('<info>' . $message->text . '</info>'),
                };
            }

            return $result->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }
}
```

ConfigShowCommand and ConfigValidateCommand follow the same shape with their
respective request DTOs and runners.

### DI registration

```yaml
# Runners
Cpsit\QualityTools\Console\Runner\ConfigInitRunner:
  arguments:
    $templateGenerator: '@Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator'
    $configLoader: '@Cpsit\QualityTools\Configuration\ConfigurationLoader'
    $filesystemService: '@Cpsit\QualityTools\Service\FilesystemService'
    $projectEnv: '@Cpsit\QualityTools\Service\ProjectEnvironment'

Cpsit\QualityTools\Console\Runner\ConfigShowRunner:
  arguments:
    $configLoader: '@Cpsit\QualityTools\Configuration\ConfigurationLoader'
    $projectEnv: '@Cpsit\QualityTools\Service\ProjectEnvironment'

Cpsit\QualityTools\Console\Runner\ConfigValidateRunner:
  arguments:
    $configLoader: '@Cpsit\QualityTools\Configuration\ConfigurationLoader'
    $projectEnv: '@Cpsit\QualityTools\Service\ProjectEnvironment'

# Commands
Cpsit\QualityTools\Console\Command\ConfigInitCommand:
  arguments:
    $runner: '@Cpsit\QualityTools\Console\Runner\ConfigInitRunner'
    $name: 'config:init'
    $description: 'Initialize YAML configuration file'
    $help: 'Creates a .quality-tools.yaml configuration file in the project root.'
  tags:
    - { name: 'console.command' }

Cpsit\QualityTools\Console\Command\ConfigShowCommand:
  arguments:
    $runner: '@Cpsit\QualityTools\Console\Runner\ConfigShowRunner'
    $name: 'config:show'
    $description: 'Show resolved configuration'
    $help: 'Shows the resolved configuration after merging all sources.'
  tags:
    - { name: 'console.command' }

Cpsit\QualityTools\Console\Command\ConfigValidateCommand:
  arguments:
    $runner: '@Cpsit\QualityTools\Console\Runner\ConfigValidateRunner'
    $name: 'config:validate'
    $description: 'Validate YAML configuration file'
    $help: 'Validates the quality-tools.yaml configuration file against the schema.'
  tags:
    - { name: 'console.command' }
```

## Migration Strategy

### Phase 1: Extract ConfigurationTemplateGenerator -- Done

- [x] Create `src/Configuration/ConfigurationTemplateGenerator.php`
- [x] Move template logic and project name detection from ConfigInitCommand
- [x] Unit tests for template generation, project detection, validation

### Phase 2: Create runners and DTOs -- Done

- [x] Create `src/Console/Runner/DTO/` namespace with:
  - `ConfigInitRequest`, `ConfigShowRequest`, `ConfigValidateRequest`
  - `CommandRunDescription`
- [x] Create `src/Console/Runner/` namespace with:
  - `ConfigInitRunner`, `ConfigShowRunner`, `ConfigValidateRunner`
- [x] Move business logic from commands into runners
- [x] Unit tests for all three runners
- [x] Move Tool/Runner DTOs to `Tool/Runner/DTO/` sub-namespace:
  - `ToolRunRequest`, `ToolRunResult`, `ToolRunDescription`
- [x] Move validation logic to proper domain classes:
  - `ConfigShowRunner::validateCriticalConfigurationFiles` removed (duplicated ConfigurationLoader)
  - `ConfigValidateRunner::validateConfigFilePaths` moved to `ConfigurationValidator::validateToolConfigFilePaths`
  - `SecurityService` dependency removed from `ConfigShowRunner`

### Phase 3: Rewrite commands -- In Progress

- [ ] Rewrite ConfigShowCommand extending Command directly, injecting ConfigShowRunner
- [ ] Rewrite ConfigInitCommand extending Command directly, injecting ConfigInitRunner
- [ ] Rewrite ConfigValidateCommand extending Command directly, injecting ConfigValidateRunner
- [ ] Remove #[AsCommand] attributes (name injected via constructor)
- [ ] Update services.yaml command registrations
- [ ] Rewrite command tests

### Phase 4: Delete BaseCommand and dependencies

- [ ] Delete `src/Console/Command/BaseCommand.php`
- [ ] Delete `src/DependencyInjection/ContainerAwareInterface.php`
- [ ] Delete `src/DependencyInjection/ContainerAwareTrait.php`
- [ ] Delete `src/Service/CommandBuilder.php`
- [ ] Delete `src/Service/ProcessEnvironmentPreparer.php`
- [ ] Remove `executeProcess()` from ProcessExecutor
- [ ] Delete test files:
  - `tests/Unit/Console/Command/BaseCommandTest.php`
  - `tests/Unit/Console/Command/BaseCommandEdgeCasesTest.php`
  - `tests/Unit/Console/Command/BaseCommandPathResolutionTest.php`
  - `tests/Integration/Console/Command/BaseCommandIntegrationTest.php`
  - `tests/Unit/Service/CommandBuilderTest.php`
  - `tests/Unit/Service/ProcessEnvironmentPreparerTest.php`
- [ ] Verify full test suite and linters

## Files Created

| File                                                   | Phase   |
|--------------------------------------------------------|---------|
| `src/Configuration/ConfigurationTemplateGenerator.php` | Phase 1 |
| `src/Console/Runner/DTO/ConfigInitRequest.php`         | Phase 2 |
| `src/Console/Runner/DTO/ConfigShowRequest.php`         | Phase 2 |
| `src/Console/Runner/DTO/ConfigValidateRequest.php`     | Phase 2 |
| `src/Console/Runner/DTO/CommandRunDescription.php`     | Phase 2 |
| `src/Console/Runner/ConfigInitRunner.php`              | Phase 2 |
| `src/Console/Runner/ConfigShowRunner.php`              | Phase 2 |
| `src/Console/Runner/ConfigValidateRunner.php`          | Phase 2 |

## Files Moved

| From                                    | To                                         | Phase   |
|-----------------------------------------|--------------------------------------------|---------|
| `src/Tool/Runner/ToolRunRequest.php`    | `src/Tool/Runner/DTO/ToolRunRequest.php`   | Phase 2 |
| `src/Tool/Runner/ToolRunResult.php`     | `src/Tool/Runner/DTO/ToolRunResult.php`    | Phase 2 |
| `src/Tool/Runner/ToolRunDescription.php`| `src/Tool/Runner/DTO/ToolRunDescription.php`| Phase 2 |

## Files Modified

| File                                              | Phase   |
|---------------------------------------------------|---------|
| `src/Configuration/ConfigurationValidator.php`    | Phase 2 |
| `src/Console/Command/ConfigInitCommand.php`       | Phase 3 |
| `src/Console/Command/ConfigShowCommand.php`       | Phase 3 |
| `src/Console/Command/ConfigValidateCommand.php`   | Phase 3 |
| `config/services.yaml`                            | Phase 1-3 |

## Files Deleted

| File                                                          | Phase   |
|---------------------------------------------------------------|---------|
| `src/Console/Command/BaseCommand.php`                         | Phase 4 |
| `src/Console/Command/AbstractToolCommand.php`                 | Done (023) |
| `src/Console/Command/ToolCommandInterface.php`                | Done (023) |
| `src/DependencyInjection/ContainerAwareInterface.php`         | Phase 4 |
| `src/DependencyInjection/ContainerAwareTrait.php`             | Phase 4 |
| `src/Service/CommandBuilder.php`                              | Phase 4 |
| `src/Service/ProcessEnvironmentPreparer.php`                  | Phase 4 |
| `tests/Unit/Console/Command/BaseCommandTest.php`              | Phase 4 |
| `tests/Unit/Console/Command/BaseCommandEdgeCasesTest.php`     | Phase 4 |
| `tests/Unit/Console/Command/BaseCommandPathResolutionTest.php` | Phase 4 |
| `tests/Integration/Console/Command/BaseCommandIntegrationTest.php` | Phase 4 |
| `tests/Unit/Service/CommandBuilderTest.php`                   | Phase 4 |
| `tests/Unit/Service/ProcessEnvironmentPreparerTest.php`       | Phase 4 |

## Validation Plan

- [ ] All existing tests pass after each phase
- [ ] No class extends BaseCommand after Phase 3
- [ ] BaseCommand and all dependencies deleted in Phase 4
- [ ] PHPStan level 6 clean
- [ ] CS Fixer clean
- [ ] No behavioral changes from user perspective
- [ ] Config commands produce identical output before and after

## Related Issues

- [023 - BaseCommand service locator to constructor injection](023-basecommand-service-locator-to-constructor-injection.md)
- [019 - Configuration class hierarchy simplification](done/019-configuration-class-hierarchy-simplification.md)
