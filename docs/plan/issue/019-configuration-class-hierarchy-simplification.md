# Issue 019: Configuration Class Hierarchy Simplification

**Type**: Refactoring
**Priority**: Medium
**Status**: Planning
**Created**: 2026-01-11
**Estimated Effort**: Large (8-12 developer days)

## Problem Statement

The current configuration system has significant architectural issues:

1. **Duplicated Business Logic**: `Configuration` and `EnhancedConfiguration` duplicate 15+ identical methods
2. **Inconsistent Return Types**: Commands use different configuration types causing type inconsistency
3. **Missing Capabilities**: `EnhancedConfiguration` lacks path resolution methods from `Configuration`
4. **Complex Dependencies**: `HierarchicalConfigurationLoader` creates multiple internal classes
5. **Maintenance Burden**: Changes require updates in multiple places

## Current Architecture Issues

From the analysis in `docs/plan/review/2026-01-11/`:

- BaseCommand → YamlConfigurationLoader → Configuration
- ConfigShowCommand → HierarchicalConfigurationLoader → EnhancedConfiguration
- Type inconsistency across command hierarchy
- 15+ duplicated method implementations

## Proposed Solution

### Goal Architecture

Simplify to two main classes:
1. **Configuration + EnhancedConfiguration** → **Configuration** (unified)
2. **YamlConfigurationLoader + HierarchicalConfigurationLoader** → **ConfigurationLoader** (unified)

### Evolutionary Refactoring Strategy

Use wrapper pattern to minimize regression risk with gradual migration.

## Implementation Plan

### Phase 1: Create Interface and Wrapper Infrastructure

#### Step 1.1: Create Interfaces
- [x] Create `ConfigurationInterface` with all methods from both implementations
- [x] Create `ConfigurationLoaderInterface` with all loader methods
- [x] Add interfaces to service container configuration
- [x] Validate interface completeness with existing implementations

#### Step 1.2: Rename Existing Classes
- [x] Rename `Configuration` → `SimpleConfiguration`
- [x] Rename `YamlConfigurationLoader` → `SimpleConfigurationLoader`
- [x] Update all imports and usages (40+ files updated)
- [x] Update test file names and class names to match
- [x] Fix all type annotation errors in tests
- [x] Run full test suite to ensure no regressions (597 unit tests passing)

#### Step 1.3: Create ConfigurationWrapper
- [x] Implemented ConfigurationWrapper class with complete interface coverage
- [x] Delegates all 76 interface methods to wrapped instance appropriately
- [x] Handles missing methods gracefully (enhanced-only methods return defaults for simple)
- [x] Added utility methods for wrapper introspection

#### Step 1.4: Create ConfigurationLoaderWrapper
- [x] Implemented ConfigurationLoaderWrapper class with complete interface coverage
- [x] Delegates all 12 loader interface methods based on mode (simple/hierarchical)
- [x] Provides utility methods for mode switching and introspection
- [x] Updated service container configuration to use wrappers
- [x] Interface bindings now point to wrapper classes
- [x] All 706 tests passing (597 unit + 109 integration)

### Phase 2: Replace All Usages

#### Step 2.1: Update Dependency Injection
- [x] Update service container to bind interfaces to wrappers
- [x] Configure BaseCommand to receive `ConfigurationLoaderInterface`
- [x] Configure ConfigShowCommand to receive `ConfigurationLoaderInterface` in hierarchical mode
- [x] Test DI switching between simple and hierarchical modes
- [x] All 706 tests passing (597 unit + 109 integration)
- [x] Commands working correctly with wrapper-based DI

#### Step 2.2: Update BaseCommand
- [x] Change constructor to accept `ConfigurationLoaderInterface`
- [x] Update `getConfiguration()` method to return `ConfigurationInterface` (already done)
- [x] Added constructor injection with backward compatibility
- [x] Updated `getConfigurationLoader()` to use injected dependency first
- [x] Verify all existing functionality works unchanged
- [x] Test path resolution methods work correctly
- [x] All 706 tests passing (597 unit + 109 integration)

#### Step 2.3: Update ConfigShowCommand
- [x] Change constructor to accept `ConfigurationLoaderInterface`
- [x] Update execute method to use interface return types
- [x] Added explicit constructor with hierarchical dependency injection
- [x] Updated variable naming for clarity ($configuration vs $enhancedConfiguration)
- [x] Maintain all source tracking and metadata capabilities
- [x] Test hierarchical features work through interface
- [x] All 706 tests passing (597 unit + 109 integration)
- [x] Verbose output shows configuration sources correctly

#### Step 2.4: Update All Other Commands
- [x] ConfigInitCommand - change to use `ConfigurationLoaderInterface`
- [x] ConfigValidateCommand - change to use `ConfigurationLoaderInterface`
- [x] AbstractToolCommand - added constructor with `ConfigurationLoaderInterface`
- [x] All tool commands inheriting from BaseCommand (automatically inherit interface usage)
- [x] Updated constructors to accept ConfigurationLoaderInterface parameter
- [x] All commands maintain backward compatibility
- [x] All 706 tests passing (597 unit + 109 integration)
- [x] Commands validated: config:init, config:validate, lint:rector, lint:phpstan

#### Step 2.5: Update Tests
- [x] Update unit tests to use interface mocking
- [x] Create contract tests for both interface implementations
- [x] Update integration tests to test both simple and hierarchical modes
- [x] Ensure 100% test coverage maintained

#### Step 2.6: Validate DI Switching
- [x] Test switching between simple and hierarchical in container config
- [x] Verify rollback capability by switching back to simple mode
- [x] Performance benchmark both configurations
- [x] Validate all commands work identically regardless of implementation

### Phase 3: Implement Factory Pattern for Loader Selection

#### Step 3.1: Create ConfigurationLoaderFactory
- [ ] Implement factory pattern that chooses loader based on context
- [ ] Support command-specific loader selection (simple for tools, hierarchical for config commands)
- [ ] Maintain interface contract for all loader methods

#### Step 3.2: Update Service Container for Factory Pattern
```php
// Service container configuration for factory pattern
$container->configure([
    // Register both loaders
    SimpleConfigurationLoader::class => SimpleConfigurationLoader::class,
    HierarchicalConfigurationLoader::class => HierarchicalConfigurationLoader::class,

    // Factory chooses loader based on command context
    ConfigurationLoaderInterface::class => ConfigurationLoaderFactory::class,

    // Configure factory with command-specific modes
    'config.loader.mode.base' => 'simple',
    'config.loader.mode.config_show' => 'hierarchical',
]);
```

#### Step 3.3: Test Factory Pattern
- [ ] Test factory selects correct loader for each command type
- [ ] Verify all loader interface methods work correctly
- [ ] Test switching between modes via configuration
- [ ] Validate performance with factory pattern

### Phase 4: Eliminate Duplicated Logic

#### Step 4.1: Extract Business Logic Services
Create dedicated service classes:

```php
class ProjectConfigService
{
    public function getPhpVersion(array $data): string
    public function getProjectName(array $data): ?string
    public function getTypo3Version(array $data): string
}

class ToolConfigService
{
    public function getToolConfig(array $data, string $tool): array
    public function isToolEnabled(array $data, string $tool): bool
    public function getToolPaths(array $data, string $tool): array
}

class PathResolutionService
{
    public function getResolvedPathsForTool(array $data, string $tool, string $projectRoot): array
    public function getScanPaths(array $data): array
    public function getExcludePaths(array $data): array
}
```

#### Step 4.2: Move Logic from Wrapper to Services
- [ ] Replace duplicated methods in ConfigurationWrapper with service calls
- [ ] Inject services via constructor or create factory
- [ ] Maintain backward compatibility during transition

#### Step 4.3: Add Missing Capabilities
- [ ] Add path resolution to enhanced variant through PathResolutionService
- [ ] Ensure ConfigurationWrapper provides all capabilities regardless of variant

### Phase 5: Unify Implementations

#### Step 5.1: Create Unified Configuration Class
```php
class Configuration
{
    private array $data;
    private array $sourceMap;
    private array $conflicts;
    private array $mergeSummary;
    private bool $hierarchicalMode;

    public function __construct(
        array $data,
        array $sourceMap = [],
        array $conflicts = [],
        array $mergeSummary = [],
        bool $hierarchicalMode = false
    ) {
        $this->data = $data;
        $this->sourceMap = $sourceMap;
        $this->conflicts = $conflicts;
        $this->mergeSummary = $mergeSummary;
        $this->hierarchicalMode = $hierarchicalMode;
    }

    // All business logic delegates to services
    // Source tracking available when hierarchicalMode = true
}
```

#### Step 5.2: Create Unified ConfigurationLoader
```php
class ConfigurationLoader
{
    public function __construct(
        private ConfigurationValidator $validator,
        private SecurityService $securityService,
        private FilesystemService $filesystemService,
        private ProjectConfigService $projectConfigService,
        private ToolConfigService $toolConfigService,
        private PathResolutionService $pathResolutionService
    ) {}

    public function load(
        string $projectRoot,
        array $commandLineOverrides = [],
        bool $hierarchical = false
    ): Configuration {
        return $hierarchical
            ? $this->loadHierarchical($projectRoot, $commandLineOverrides)
            : $this->loadSimple($projectRoot);
    }
}
```

### Phase 6: Final Cleanup

#### Step 6.1: Replace Wrapper with Unified Classes
- [ ] Update all code to use unified `Configuration` and `ConfigurationLoader`
- [ ] Remove `ConfigurationWrapper` and `ConfigurationLoaderWrapper`
- [ ] Remove old `SimpleConfiguration`, `EnhancedConfiguration`, etc.

#### Step 6.2: Update Documentation
- [ ] Update developer documentation
- [ ] Update API documentation
- [ ] Update configuration guide

## Risk Mitigation

### Testing Strategy
1. **Comprehensive Test Coverage**: Maintain 100% coverage throughout refactoring
2. **Integration Tests**: Ensure all command combinations work correctly
3. **Backward Compatibility**: Each phase must maintain existing functionality
4. **Regression Testing**: Run full test suite after each step

### Rollback Plan
Each phase can be independently rolled back:
- Phase 1-2: Remove wrappers, revert to original classes
- Phase 3: Revert loader changes
- Phase 4-5: Revert service extraction
- Phase 6: Keep wrappers if unified classes have issues

### Validation Criteria
- [ ] All existing tests pass
- [ ] No functional regressions
- [ ] Performance maintained or improved
- [ ] Memory usage not increased
- [ ] All commands work identically to before

## Benefits

1. **Reduced Complexity**: Single configuration class instead of two
2. **Eliminated Duplication**: Business logic centralized in services
3. **Type Consistency**: All commands use same configuration interface
4. **Clean DI Switching**: Change implementations via container configuration only
5. **Rollback Safety**: Zero-risk rollback by changing DI bindings
6. **Interface Contract**: Type safety during complex refactoring transitions
7. **Improved Testability**: Services can be mocked independently via interfaces
8. **Better Maintainability**: Changes in one place instead of multiple

## Timeline

- **Phase 1**: 3-4 days (interface creation + wrapper infrastructure)
- **Phase 2**: 3-4 days (replace all usages + DI switching)
- **Phase 3**: 2-3 days (factory pattern + testing)
- **Phase 4**: 3-4 days (extract services to eliminate duplication)
- **Phase 5**: 2-3 days (unify implementations)
- **Phase 6**: 1-2 days (cleanup + documentation)

**Total**: 14-18 days (revised estimate with interface benefits)

## Success Criteria

- [ ] Single `Configuration` class handles all use cases
- [ ] Single `ConfigurationLoader` class with mode parameter
- [ ] All commands use `ConfigurationInterface` and `ConfigurationLoaderInterface`
- [ ] Clean DI switching between implementations validated
- [ ] No duplicated business logic (moved to services)
- [ ] All tests pass with interface-based contract testing
- [ ] No functional changes from user perspective
- [ ] Rollback capability tested and validated
- [ ] Improved code maintainability metrics

## Dependencies

- Requires completion of current configuration system testing
- Should be done after any pending configuration-related features
- Coordinate with any parallel development affecting configuration

## Notes

This refactoring follows the Strangler Fig pattern - gradually replacing the old system while maintaining functionality. Each phase is designed to be independently deployable and rollback-capable.

**Key Architectural Decision**: The addition of interfaces provides critical benefits during refactoring:
- **Clean Dependency Injection Switching**: Commands can switch between implementations via container configuration only
- **Type Safety**: Interface contracts ensure wrapper implementations are complete
- **Risk Mitigation**: Zero-risk rollback by changing DI bindings
- **Testing Benefits**: Interface mocking and contract testing

The interfaces are not over-engineering but essential infrastructure for safe evolutionary refactoring at this complexity level.

## Current Issues (Phase 1 Step 1.1)

### Test Failures (3 failures)
**Root Cause**: Pre-existing test fragility in `ConfigValidateCommandTest` - NOT related to interface changes.

**Issue**: Tests expect output to contain only filename (e.g., `.quality-tools.yaml`) but `ConfigValidateCommand` outputs full absolute path from `findConfigurationFile()`. In environments with long temp directory paths, the string matching fails.

**Affected Tests**:
- `testExecuteWithValidConfiguration` (line 89)
- `testExecuteWithQualityToolsYamlFile` (line 252)
- `testExecuteWithQualityToolsYmlFile` (line 273)

**Environment Sensitivity**: Works in some IDE environments, fails in CLI with long temp paths.

**Fix Required**: Update test assertions to handle full paths or extract filenames before comparison.

### Linting Failures (65 issues in 10 files)
**Root Cause**: EditorConfig violations from interface implementation work.

**Issues**:
- Trailing whitespaces in modified files
- Missing final newlines in interface files

**Files**: ConfigurationInterface.php, ConfigurationLoaderInterface.php, documentation files, test files

### PHPStan Type Errors (15 errors)
**Root Cause**: Type declaration mismatches after interface implementation.

**Issues**:
- `BaseCommand::$configuration` property typed as `Configuration|null` but receives `ConfigurationInterface`
- Test methods expect concrete `Configuration` return type but get `ConfigurationInterface`
- `ConfigurationBuilder` constructor expects `Configuration` but receives `ConfigurationInterface`

**Resolution**: Update type declarations to use interface types where appropriate.

## Todo Before Phase 1 Step 1.2
- [ ] Fix ConfigValidateCommandTest path matching issues
- [ ] Fix EditorConfig violations (trailing spaces, final newlines)
- [ ] Fix PHPStan type mismatches
- [ ] Update BaseCommand property type to ConfigurationInterface
- [ ] Update test return type expectations
- [ ] Verify all quality checks pass
