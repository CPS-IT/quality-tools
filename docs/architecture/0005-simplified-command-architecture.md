# [ADR-0005] Simplified command architecture with DTO-based tool runners

## Status

Accepted

## Context

The current command layer has grown into a 3-level inheritance hierarchy
(BaseCommand -> AbstractToolCommand -> ConcreteCommand) totalling ~900 lines of
base class code. BaseCommand carries 7 service-locator dependencies, a template
method pattern with hook points, optimization logic, memory calculation, vendor
detection, path resolution, and configuration discovery -- responsibilities that
do not belong in a console command.

The concrete tool commands are thin, but they inherit the full weight of the
hierarchy. Config commands share a base class with tool commands despite having
almost nothing in common.

Issue 023 proposed extracting focused service objects from BaseCommand. While
directionally correct, it adds more classes without simplifying the command
layer itself. The root cause is not "too many dependencies in BaseCommand" but
"too many responsibilities in the command layer".

A command should do exactly this:

1. Unwrap arguments and options from input
2. Pass them to a specialized executor
3. Receive structured results
4. Give the user feedback

There are no interactive commands. The flow is linear.

## Decision

We will replace the current BaseCommand/AbstractToolCommand hierarchy with a
flat structure where commands are thin input/output adapters and tool execution
logic lives in dedicated runner classes behind a common interface.

### Architecture overview

```
Command layer (Symfony Console)
  RectorCommand (parameterized, registered twice via DI), PhpStanCommand, ...
  - Dependencies: ToolRunnerRegistry + ToolRunInfoDisplay
  - Unwraps input into ToolRunRequest DTO
  - Creates StreamingOutputCollector from OutputInterface
  - Calls describe() to render pre-run info via ToolRunInfoDisplay
  - Calls runner, renders ToolRunResult messages

Runner layer (no framework dependency)
  RectorRunner, PhpStanRunner, ...
  - Implements ToolRunnerInterface
  - Receives ToolRunRequest (user intent) + OutputCollectorInterface (output sink)
  - Owns path resolution, config discovery, command building
  - Returns ToolRunResult with exit code and diagnostic messages
  - Exposes describe(ToolRunRequest): ToolRunDescription for pre-run info
  - Composes services via constructor injection (ProcessExecutor, ProjectEnvironment, MemoryOptimizer, ...)

Service layer (unchanged)
  ProcessExecutor, ProjectEnvironment, MemoryOptimizer, ConfigurationLoader, FilesystemService, ...
```

### Key design choices

**ToolRunRequest carries user intent, not resolved state.** The request DTO
contains only what the user provided: tool name, dry-run flag, optional config
override, optional path override, and tool-specific options. The runner resolves
project root, vendor paths, config paths, and target paths internally via
the services it composes. This keeps the command layer free of resolution
dependencies.

**OutputCollectorInterface decouples runners from Symfony Console.** Runners
produce live process output (stdout/stderr streaming) via an
`OutputCollectorInterface`. The command layer provides a
`StreamingOutputCollector` that forwards to `OutputInterface` in real time.
Tests use a `BufferingOutputCollector` that stores output in memory. The runner
has no knowledge of how output is delivered.

**ToolRunResult carries runner diagnostics.** The result DTO contains the exit
code and a list of `Message` objects (info/warning/error with context, using
`MessageSeverity`). Live process output flows through the collector;
runner-level diagnostics (resolved paths, missing files, validation summaries)
go into the result.

**Parameterized commands eliminate lint/fix duplication.** One `RectorCommand`
class is registered twice in `services.yaml` with different `$dryRun`, `$name`,
`$description`, and `$help` constructor values. The file scanner excludes the
class from automatic registration; `registerRunnerCommands()` resolves both
instances by suffixed service IDs. This removes the need for separate
`RectorLintCommand` and `RectorFixCommand` classes.

**describe() enables pre-run info display.** `ToolRunnerInterface` exposes a
`describe(ToolRunRequest): ToolRunDescription` method that returns the resolved
config path, target paths, metrics, and memory limit without executing the
tool. `ToolRunInfoDisplay` renders this information to the console before the
run begins. This replaces `BaseCommand::showOptimizationDetails()`.

**Memory optimization is composed, not inherited.** `MemoryCalculator` and
`ProjectAnalyzer` are kept but composed into a `MemoryOptimizer` service that
is injected into runners that need it. `MemoryOptimizer::analyzeAndAggregate()`
provides the metrics used by `describe()`. Commands no longer inherit memory
logic from a base class.

**Config auto-discovery via ConfigurationLoader.** A
`resolveToolConfigPath(projectRoot, toolName): ?string` method is added to
`ConfigurationLoaderInterface`. Runners call it before falling back to package
defaults. This eliminates the duplicated `AbstractToolCommand::resolveConfigPath`
logic that was previously copied across the command layer.

**One runner per tool, no inheritance between runners.** Each runner is a
self-contained class. Shared behavior (process execution, path resolution) is
composed via injected services, not inherited. Tool-specific logic (YAML
pre-validation for Fractor, temporary configs for PHPStan, multi-file iteration
for Composer) lives in the runner that needs it.

**Config commands are fully separate.** ConfigInit, ConfigShow, and
ConfigValidate have nothing in common with tool commands. They keep their own
structure with direct service injection.

### Components

**DTOs** (`src/Tool/Runner/`):
- `ToolRunRequest` -- immutable, user intent only
- `ToolRunResult` -- immutable, exit code + messages
- `ToolRunDescription` -- immutable, resolved config path, target paths, metrics, memory limit

**DTOs** (`src/Messaging/`):
- `Message` -- immutable, severity + text + context
- `MessageSeverity` -- enum (info, warning, error)

**Interfaces** (`src/Tool/Runner/`):
- `ToolRunnerInterface` -- `run(ToolRunRequest, OutputCollectorInterface): ToolRunResult` + `describe(ToolRunRequest): ToolRunDescription` + `supportedTools(): list<string>`

**Interfaces** (`src/Messaging/`):
- `OutputCollectorInterface` -- `write(string, MessageSeverity): void` + `writeError(string): void`

**Collector implementations** (`src/Messaging/`):
- `StreamingOutputCollector` -- wraps OutputInterface, forwards immediately
- `BufferingOutputCollector` -- stores in memory for tests

**Registry** (`src/Tool/Runner/`):
- `ToolRunnerRegistry` -- maps tool names to runner instances via DI tagging

**Runners** (`src/Tool/Runner/`):
- `RectorRunner`, `PhpStanRunner`, `PhpCsFixerRunner`, `FractorRunner`, `TypoScriptLintRunner`, `ComposerNormalizeRunner`

**Display** (`src/Console/Output/`):
- `ToolRunInfoDisplay` -- renders `ToolRunDescription` to the console before a run

**Services** (`src/Service/`):
- `ProjectEnvironment` (new) -- project root + vendor path detection, supports any Composer project
- `MemoryOptimizer` (new) -- composes MemoryCalculator and ProjectAnalyzer, exposes `analyzeAndAggregate()`
- `ProcessExecutor` (adapted) -- gains `OutputCollectorInterface`-based method

### What gets removed

- `BaseCommand` (500 lines), `AbstractToolCommand` (410 lines), `FractorCommandTrait`
- `ContainerAwareInterface`, `ContainerAwareTrait`
- `CommandBuilder`, `ProcessEnvironmentPreparer`, `ErrorHandler`
- `ToolCommandInterface`
- `RectorLintCommand`, `RectorFixCommand` (replaced by parameterized `RectorCommand`)

### What stays

- `ProcessExecutor` (adapted), `FilesystemService`, `SecurityService`
- `VendorDirectoryDetector` (used by ProjectEnvironment)
- `ConfigurationLoader`, `ErrorFactory`
- `MemoryCalculator`, `ProjectAnalyzer` (composed into MemoryOptimizer)
- Config commands (independent)

## Consequences

### Positive

- Commands become ~30 lines with two dependencies (ToolRunnerRegistry, ToolRunInfoDisplay)
- Each tool's logic is self-contained in its runner
- DTOs make the data flow explicit and type-safe
- Runners are testable without Symfony Console (BufferingOutputCollector)
- No service locator pattern -- pure constructor injection
- Config commands fully decoupled from tool commands
- Adding a new tool: one runner class + one command class
- Runners are usable outside CLI context (CI scripts, programmatic usage)
- Parameterized RectorCommand eliminates lint/fix class duplication

### Negative

- Migration effort across 10 commands + tests
- Minor duplication in command option definitions (2 options across 10 commands)
- ProcessExecutor signature change during transition

### Neutral

- File count roughly stable (remove base classes + DI traits, add runners + DTOs)
- DI configuration needs updating for runner registration

## Alternatives Considered

### Alternative 1: Extract focused service objects (original Issue 023 plan)

Extract ProcessRunner, OptimizationService, ProjectEnvironment from BaseCommand,
then inject into commands. Rejected: adds indirection without simplifying the
command layer. The template method pattern and hook points remain.

### Alternative 2: Inject all 7 services as constructor parameters

Replace service locator with constructor injection in BaseCommand. Rejected:
an 8-parameter constructor signals the class does too much. Config commands
would still receive 7 unused services.

### Alternative 3: Pass OutputInterface directly to runners

Rejected: couples the runner layer to Symfony Console, prevents non-CLI usage,
requires OutputInterface mocks in tests.

### Alternative 4: Pass resolved paths in ToolRunRequest

Rejected: forces the command to resolve paths before calling the runner,
requiring ConfigurationLoader, ProjectEnvironment, and FilesystemService as
command dependencies. Keeps resolution logic in the wrong layer.

## Open Questions

**Memory optimization**: Resolved. MemoryCalculator and ProjectAnalyzer are
kept and composed into a `MemoryOptimizer` service. This avoids dropping
existing logic while removing the inheritance-based delivery mechanism.

**TYPO3 project detection**: Resolved. `ProjectEnvironment` supports any
Composer project via `VendorDirectoryDetector`. TYPO3-specific assumptions
have been removed from the environment detection logic.

## References

- [Issue 023 - Implementation plan](../plan/issue/done/023-basecommand-service-locator-to-constructor-injection.md)
- [ADR-0003 - Code Duplication Elimination Through Refactoring](0003-code-duplication-elimination-through-refactoring.md)
