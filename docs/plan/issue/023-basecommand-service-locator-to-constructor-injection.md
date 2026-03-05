# Issue 023: BaseCommand Service Locator to Constructor Injection

**Status:** Open
**Priority:** Medium
**Effort:** High (1-3d)
**Impact:** High

## Description

BaseCommand uses a service locator anti-pattern for 7 dependencies: each is resolved via
`$this->getService()` with a `new Instance()` fallback. This creates hidden coupling,
makes testing harder than necessary, and inflates the class with responsibilities that
do not belong in a command.

The 7 service getter methods are:

| Method | Creates | Visibility |
|---|---|---|
| `getVendorDirectoryDetector()` | `VendorDirectoryDetector` | protected |
| `getProcessEnvironmentPreparer()` | `ProcessEnvironmentPreparer` | private |
| `getCommandBuilder()` | `CommandBuilder` | private |
| `getProcessExecutor()` | `ProcessExecutor` | private |
| `getProjectAnalyzer()` | `ProjectAnalyzer` | private |
| `getFilesystemService()` | `FilesystemService` | protected |
| `getMemoryCalculator()` | `MemoryCalculator` | protected |

## Root Cause

BaseCommand grew organically as the single base class for all commands. It accumulated
multiple responsibilities: process execution, path resolution, vendor detection,
configuration loading, project analysis, memory optimization, and optimization reporting.

All 10 tool commands (via AbstractToolCommand) transitively depend on all 7 services,
because the template method in AbstractToolCommand calls `showOptimizationDetails()`,
`resolveTargetPaths()`, `getToolMemoryLimit()`, and `executeProcess()` -- which
collectively touch every service getter.

Config commands (ConfigInitCommand, ConfigShowCommand, ConfigValidateCommand) need
almost none of these services -- they primarily use `configurationLoader` and
`filesystemService`.

## Impact Analysis

**Affected Components:**
- `BaseCommand` (src/Console/Command/BaseCommand.php)
- `AbstractToolCommand` (src/Console/Command/AbstractToolCommand.php)
- All 12 concrete command classes
- `ContainerAwareInterface` / `ContainerAwareTrait`
- `ServiceContainer` / `services.yaml`
- All command tests (unit and integration)

**User Impact:**
- None -- internal refactoring, no behavioral changes

**Technical Impact:**
- Service locator pattern makes dependencies invisible at the type level
- Tests must either set up a DI container or rely on fallback instantiation (hidden coupling)
- BaseCommand carries 500+ lines of mixed responsibilities
- Subclasses inherit the entire dependency surface even when they need a fraction of it
- PhpStanCommand creates its own SecurityService and FilesystemService inline, bypassing the
  getter pattern entirely -- a symptom of the unclear ownership

## Dependency Graph (Current)

```
BaseCommand
  - ConfigurationLoaderInterface  (constructor-injected -- already done)
  - VendorDirectoryDetector       (service locator)
  - ProcessEnvironmentPreparer    (service locator)
  - CommandBuilder                (service locator)
  - ProcessExecutor               (service locator)
  - ProjectAnalyzer               (service locator)
  - FilesystemService             (service locator)
  - MemoryCalculator              (service locator)

AbstractToolCommand extends BaseCommand
  - transitively uses ALL of the above

ConfigInitCommand extends BaseCommand
  - configurationLoader, FilesystemService (constructor-injected)

ConfigShowCommand extends BaseCommand
  - configurationLoader only

ConfigValidateCommand extends BaseCommand
  - configurationLoader only
```

## Possible Solutions

### Solution 1: Inject all 7 as individual constructor parameters

- **Description:** Replace each getter with a constructor parameter
- **Effort:** Medium
- **Impact:** Removes service locator, enables ContainerAwareTrait removal
- **Pros:** Explicit dependencies, testable, straightforward
- **Cons:** Constructor grows to 8 parameters -- still a code smell; does not address
  the underlying responsibility bloat; config commands receive 7 services they never use

### Solution 2: Bundle into a single CommandServices DTO

- **Description:** Group the 7 services into a readonly DTO, inject that
- **Effort:** Medium
- **Impact:** Cleaner constructor, but hides the dependency count
- **Pros:** Lean constructor, easy to pass in tests
- **Cons:** Does not reduce coupling -- just wraps it; config commands still receive
  everything; the DTO is a bag of unrelated services

### Solution 3: Extract focused service objects, then inject (recommended)

- **Description:** Group related services into cohesive higher-level services that
  own a single responsibility. Then inject only what each command actually needs.
- **Effort:** High
- **Impact:** Addresses the root cause -- reduces BaseCommand to a thin base class

**Proposed service groups:**

1. **ProcessRunner** -- composes `ProcessExecutor`, `ProcessEnvironmentPreparer`,
   `CommandBuilder`. Single method: `run(command, projectRoot, input, output, memoryLimit, tool, resolvedPaths): int`.
   Replaces `executeProcess()` in BaseCommand.

2. **OptimizationService** -- composes `ProjectAnalyzer`, `MemoryCalculator`.
   Methods: `getOptimalMemoryLimit()`, `shouldEnableParallelProcessing()`,
   `showOptimizationDetails()`. Replaces the optimization block in BaseCommand.

3. **ProjectEnvironment** -- composes `FilesystemService`, `VendorDirectoryDetector`.
   Methods: `getVendorBinPath()`, `findVendorPath()`, `getProjectRoot()`.
   Replaces the path/vendor detection in BaseCommand.

4. **ConfigurationLoaderInterface** -- stays as is (already injected).

After extraction, the dependency surface becomes:

```
AbstractToolCommand
  - ConfigurationLoaderInterface
  - ProcessRunner
  - OptimizationService
  - ProjectEnvironment

ConfigShowCommand / ConfigValidateCommand
  - ConfigurationLoaderInterface

ConfigInitCommand
  - ConfigurationLoaderInterface
  - FilesystemService
```

- **Pros:** Each service has a single responsibility; commands declare only what they
  use; BaseCommand shrinks or disappears; testability improves; ContainerAwareTrait
  can be removed
- **Cons:** More files; migration effort; requires careful phasing to avoid regressions

## Recommended Solution

**Choice:** Solution 3 -- Extract focused services, then inject

This addresses the root cause rather than just the symptom. The service locator pattern
is a consequence of BaseCommand doing too much; replacing the locator with constructor
injection alone (Solution 1) would make the bloat more visible but not fix it.

**Implementation Steps:**

### Phase 1: Extract ProcessRunner
1. Create `Service/ProcessRunner.php` composing ProcessExecutor, ProcessEnvironmentPreparer, CommandBuilder
2. Move `executeProcess()` logic from BaseCommand into ProcessRunner
3. Inject ProcessRunner into AbstractToolCommand
4. Update all tool commands and tests
5. Remove the 3 getter methods and their fallback `new` calls from BaseCommand

### Phase 2: Extract OptimizationService
1. Create `Service/OptimizationService.php` composing ProjectAnalyzer, MemoryCalculator
2. Move optimization methods from BaseCommand into OptimizationService
3. Inject OptimizationService into AbstractToolCommand
4. Update tests
5. Remove getter methods from BaseCommand

### Phase 3: Extract ProjectEnvironment
1. Create `Service/ProjectEnvironment.php` composing FilesystemService, VendorDirectoryDetector
2. Move `findVendorPath()`, `getVendorBinPath()` into ProjectEnvironment
3. Inject ProjectEnvironment into commands that need it
4. Update tests

### Phase 4: Flatten hierarchy (optional, depends on outcome of Phase 1-3)
1. Evaluate whether BaseCommand still justifies its existence
2. If only `configure()` (adding --config, --path, --no-optimization options) remains,
   consider moving that into AbstractToolCommand directly
3. Remove ContainerAwareInterface / ContainerAwareTrait if no longer used
4. Update services.yaml

## Validation Plan

- [ ] All existing tests pass after each phase
- [ ] No service locator calls remain in command classes
- [ ] ContainerAwareInterface / ContainerAwareTrait removed or justified
- [ ] Each new service class has its own unit tests
- [ ] Constructor parameter counts: AbstractToolCommand <= 4, Config commands <= 2
- [ ] PhpStan level 6 clean
- [ ] No behavioral changes from the user perspective

## Dependencies

- Issue 019 (Configuration Class Hierarchy Simplification) should be substantially
  complete before starting, to avoid conflicting changes in BaseCommand
- Issue 020 (DI Configuration Inconsistency) may be resolved as a side effect of
  this work

## Workarounds

The current service locator pattern works correctly. This issue is about code quality
and maintainability, not broken functionality.

## Related Issues

- [019 - Configuration Class Hierarchy Simplification](019-configuration-class-hierarchy-simplification.md) --
  ongoing refactoring that already made `configurationLoader` a required constructor parameter
- [020 - DI Configuration Inconsistency](020-di-configuration-inconsistency.md) --
  overlapping concern about DI wiring
- [013 - Dependency Injection Container Architecture](issue/done/013-dependency-injection-container-architecture.md) --
  introduced the current DI container and service locator pattern
- [018 - BaseCommand ExecuteProcess Method Refactoring](issue/done/018-basecommand-executeprocess-method-refactoring.md) --
  previous refactoring of executeProcess that extracted CommandBuilder, ProcessExecutor, etc.
