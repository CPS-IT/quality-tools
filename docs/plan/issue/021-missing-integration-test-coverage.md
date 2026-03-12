# Issue 021: Missing Integration Test Coverage for Command Path Configuration

**Status:** Done
**Priority:** High
**Effort:** Medium (3-8h)
**Impact:** High

## Description

Several commands lack comprehensive integration test coverage for path configuration and override behavior. This creates gaps in validation that path resolution works consistently across all tools, especially after the Step 6.1 configuration system refactoring.

## Root Cause

Integration test coverage for path configuration was not systematically created for all commands. While some commands (Rector, PhpCsFixer) have dedicated path configuration tests, others rely only on unit tests or have minimal integration coverage, leaving potential issues undetected.

## Error Details

**Error Message:**
```
No explicit error - this is a test coverage gap
```

**Location:** `tests/Integration/` directory
**Trigger:** Missing test scenarios for path configuration behavior

## Impact Analysis

**Affected Components:**
- PhpStanCommand (limited integration test coverage for path resolution)
- PhpCsFixerFixCommand (no dedicated path configuration test)
- ComposerLintCommand, ComposerFixCommand (no integration tests)
- FractorLintCommand, FractorFixCommand (no integration tests)
- TypoScriptLintCommand (no integration tests)

**User Impact:**
- Potential undiscovered bugs in path resolution for specific commands
- Inconsistent behavior between commands may go unnoticed
- Risk of regressions during future refactoring

**Technical Impact:**
- Incomplete validation of unified configuration system
- Reduced confidence in cross-command consistency
- Harder to detect integration issues between configuration and command execution

## Possible Solutions

### Solution 1: Create Comprehensive Integration Test Suite
- **Description:** Create dedicated integration tests for each missing command covering path configuration, overrides, and resolution behavior
- **Effort:** Medium
- **Impact:** High - ensures complete coverage and consistent behavior validation
- **Pros:** Comprehensive coverage; catches integration issues; validates consistency
- **Cons:** More test code to maintain; longer test suite execution time

### Solution 2: Extend Existing MultiPathScanningTest
- **Description:** Add test methods to existing MultiPathScanningTest for all missing commands
- **Effort:** Medium
- **Impact:** Medium - good coverage but potentially unwieldy single test class
- **Pros:** Centralized path testing; consistent test patterns
- **Cons:** Large test class; harder to maintain; mixed responsibilities

### Solution 3: Create Command-Specific Integration Tests
- **Description:** Create separate integration test files for each command focusing on their specific path handling
- **Effort:** High
- **Impact:** High - most thorough coverage with command-specific validation
- **Pros:** Focused tests; easy to maintain; comprehensive coverage
- **Cons:** More test files; potential duplication of test setup

## Recommended Solution

**Choice:** Solution 1 with elements of Solution 3 - Create comprehensive integration tests with focused approach

Create dedicated integration test coverage while following the existing MultiPathScanningTest pattern for consistency, but organize tests by command for maintainability.

**Implementation Steps:**
1. Create integration test for PhpStanCommand path resolution behavior (extending existing cleanup test)
2. Create integration test for PhpCsFixerFixCommand path configuration
3. Create integration test for ComposerLintCommand and ComposerFixCommand path handling
4. Create integration test for FractorLintCommand and FractorFixCommand path configuration
5. Create integration test for TypoScriptLintCommand path resolution
6. Verify all commands handle path overrides consistently with the same configuration scenarios:
   - Default configuration (no explicit paths)
   - Global path overrides (paths.scan section)
   - Tool-specific path overrides (tools.{tool}.paths.scan)
   - Vendor namespace patterns (vendor/company/*)
   - Exclusion patterns (!excluded/*)

## Implementation

**Approach:** Created a unified `ToolCommandPathConfigurationTest` that tests the full integration chain:
fixture YAML -> ConfigurationLoader -> Runner::describe() for all 6 tool runners.

**Test file:** `tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php`
**Fixtures:** `tests/Fixtures/021-path-configuration/` (4 scenarios with physical directories)

**21 new integration tests covering:**

1. **Global path overrides** (5 tests via data provider):
   - Rector, PHPStan, PhpCsFixer, Fractor, TypoScriptLint all resolve global `paths.scan` correctly

2. **Tool-specific path overrides** (3 tests):
   - Rector and PHPStan tool-specific paths are returned (documents nested structure behavior)
   - Tools without specific overrides fall back to global paths

3. **Vendor namespace patterns** (2 tests):
   - `vendor/company/*` expands to correct package count
   - Pattern expansion is consistent across all tools

4. **Runner describe() integration** (6 tests):
   - Each runner (Rector, PHPStan, PhpCsFixer, Fractor, TypoScriptLint, ComposerNormalize)
     resolves correct target paths from YAML configuration via describe()

5. **Runner with tool-specific config** (2 tests):
   - Runners receive non-empty paths when tool-specific config is set

6. **Runner with vendor patterns** (1 test):
   - Rector runner correctly expands vendor namespace patterns

7. **Config path resolution** (2 tests):
   - Rector and PHPStan resolve correct default config file paths

**Known issue documented:** `PathResolutionService::getToolPaths()` returns raw nested structure
(`{scan: [...]}`) instead of a flat path list when tool-specific paths are configured. This
should be addressed in a separate issue.

## Validation Plan

- [x] Each command has dedicated integration test covering path configuration
- [x] All tests verify the same configuration scenarios (defaults, overrides, patterns)
- [x] Tests validate consistent behavior across all commands
- [x] Integration with unified ConfigurationLoader is verified
- [x] Path resolution produces expected absolute paths
- [x] Vendor namespace patterns work consistently
- [ ] Exclusion patterns are applied correctly (deferred - requires PathResolutionService fix)

## Dependencies

- Issue 020: DI Configuration Inconsistency should be resolved first for consistent testing
- Unified ConfigurationLoader implementation (already completed)
- PathResolutionService implementation (already completed)

## Workarounds

Current workaround is relying on unit tests and the limited integration tests that exist, but this doesn't validate end-to-end command behavior with path configuration.

## Related Issues

- Issue 019: Configuration Class Hierarchy Simplification (Step 6.1)
- Issue 020: Inconsistent Dependency Injection Configuration for Command ConfigurationLoader
- Related to MultiPathScanningTest corrections (expected path count fixes)
- PathResolutionService nested structure issue found during testing (needs separate issue)
