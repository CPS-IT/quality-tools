# Issue 022: Configuration File Replacement Schema Validation Bug

- **GitLab:** GL#5 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/5)
- **GitHub:** GH#5 (https://github.com/CPS-IT/quality-tools/issues/5)
- **Resolved by:** GH PR#6 (merged 2026-03-01)

## Status
**Completed**

**Core Achievement**: Schema now supports `config_file` properties - users can specify `config_file` in YAML configurations without validation errors. The `tool_config_file` and `custom_config` metadata keys have been removed from `ConfigurationDiscovery`; tool-specific config files are now represented under the proper `quality-tools.tools.<tool>.config_file` schema path.

**Note on "Remaining Work"**: The earlier note about ConfigurationDiscovery metadata injection causing integration test failures is resolved. The code no longer injects those keys.

## Problem Summary
When users place custom configuration files for tools (e.g., `rector.php`, `phpstan.neon`) in their project root or config directory, the custom files should replace the default configurations provided by quality-tools. However, this functionality has multiple validation and execution issues:

1. `qt config:validate` reports "[OK] Configuration is valid" (false positive)
2. `qt lint:rector` ignores the custom config file and uses the default config
3. `qt config:show` fails with schema validation errors about undefined properties

## Root Cause Analysis
The issue stems from schema validation conflicts in the configuration discovery process:

**Location:** `src/Configuration/ConfigurationDiscovery.php:165` and `src/Configuration/ConfigurationDiscovery.php:178`

When tool-specific configuration files (PHP, Neon) are discovered, the system adds metadata keys to the configuration data:
- `tool_config_file` - Path to the custom config file
- `custom_config` - Boolean flag indicating custom configuration

These keys are not defined in the JSON schema at `config/schema/quality-tools.json`, causing validation failures when the merged configuration is validated.

## Steps to Reproduce

### Prerequisites
- Quality-tools package installed in a TYPO3 project
- Access to project root directory

### Test Case 1: Basic Configuration File Override
1. Create a custom rector configuration in project root:
   ```bash
   # Create rector.php in project root
   cat > rector.php << 'EOF'
   <?php
   declare(strict_types=1);
   use Rector\Config\RectorConfig;
   return static function (RectorConfig $rectorConfig): void {
       $rectorConfig->paths(['custom-path/']);
   };
   EOF
   ```

2. Test configuration validation:
   ```bash
   vendor/bin/qt config:validate
   # Expected: Configuration validation should detect schema issues
   # Actual: "[OK] Configuration is valid." (false positive)
   ```

3. Test configuration display:
   ```bash
   vendor/bin/qt config:show
   # Expected: Should show merged configuration with custom rector config
   # Actual: Schema validation error about undefined properties
   ```

4. Test rector execution:
   ```bash
   vendor/bin/qt lint:rector --dry-run
   # Expected: Should use custom rector.php configuration
   # Actual: Uses default quality-tools rector config, ignoring custom file
   ```

### Test Case 2: Config Directory Override
1. Create configuration in config directory:
   ```bash
   mkdir -p config
   cat > config/rector.php << 'EOF'
   <?php
   declare(strict_types=1);
   use Rector\Config\RectorConfig;
   return static function (RectorConfig $rectorConfig): void {
       $rectorConfig->paths(['config-custom-path/']);
   };
   EOF
   ```

2. Repeat validation and execution tests from Test Case 1
   - Same issues should occur

### Test Case 3: Multiple Tool Configurations
1. Add phpstan.neon alongside rector.php:
   ```bash
   cat > phpstan.neon << 'EOF'
   parameters:
     level: 2
     paths:
       - custom-phpstan-path/
   EOF
   ```

2. Test combined configuration:
   ```bash
   vendor/bin/qt config:show
   # Expected: Should handle multiple custom configs
   # Actual: Schema validation fails for both tool_config_file entries
   ```

## Expected Behavior
1. **Configuration Validation**: Should properly validate merged configurations that include custom tool config file metadata
2. **Tool Execution**: Tools should use custom configuration files when present, falling back to defaults when not
3. **Configuration Display**: Should show merged configuration including custom tool config paths without schema errors
4. **Priority Handling**: Custom configs should override defaults with clear precedence rules

## Affected Components
- `src/Configuration/ConfigurationDiscovery.php` - Adds undefined schema keys
- `src/Configuration/ConfigurationValidator.php` - Validates against incomplete schema
- `config/schema/quality-tools.json` - Missing tool_config_file and custom_config properties
- Tool-specific loaders - May not respect custom configuration files
- CLI commands: `config:validate`, `config:show`, all `lint:*` commands

## Impact Assessment
**Priority:** High - Core functionality broken

**User Impact:**
- Users cannot override default tool configurations
- False positive validation results create confusion
- Configuration introspection commands fail
- Reduced flexibility in tool customization

**Development Impact:**
- Configuration system reliability compromised
- Integration testing gaps exposed
- Documentation accuracy affected

## Technical Debt
This issue reveals broader problems in the configuration system:
1. Schema validation and configuration discovery are not properly integrated
2. Tool-specific configuration handling is inconsistent
3. Missing test coverage for configuration file replacement scenarios
4. Validation feedback mechanisms need improvement

## Related Issues
- Issue 019: Configuration class hierarchy simplification (current refactoring)
- Feature 031: Enhanced schema validation (extracted advanced patterns)
- Manual testing fixtures and scenarios: [`tests/Fixtures/022-testing-plan/README.md`](../../../../tests/Fixtures/022-testing-plan/README.md)
- Automated test fixtures: [`tests/Fixtures/configFileReplacement/`](../../../../tests/Fixtures/configFileReplacement/)

## Scope Clarification
**Current Focus**: Integration of refactored secure path resolution services for configuration file discovery and validation.

**Moved to Feature 031**: Advanced schema validation patterns including:
- Security patterns for directory traversal prevention
- Tool-specific file extension validation
- Advanced edge case handling (whitespace, length limits)
- Complex JSON schema patterns

**Note**: UpdatedSchemaValidationTest advanced validation scenarios re-skipped until Feature 031 implementation.

## Refined Solution: Configuration File Key Approach

### Root Cause Re-Analysis
The original approach of adding runtime metadata keys to the schema was architecturally flawed. The real issue is trying to validate user YAML configuration mixed with runtime discovery metadata against a single schema.

### Proposed Solution: Explicit `config_file` Key

**Architecture:**
- Add optional `config_file` key to each tool in user YAML schema
- Use clear precedence: User-specified > Auto-discovered > Package defaults
- No runtime metadata pollution - everything is user-visible and schema-compliant
- Implement secure path resolution with validation and boundary checks
- Add configuration file validation to ensure tool compatibility

**Schema Addition:**
```json
{
  "rector_config": {
    "properties": {
      "enabled": {"type": "boolean"},
      "level": {"type": "string"},
      "config_file": {
        "type": "string",
        "description": "Path to custom configuration file (relative to project root or absolute)",
        "examples": ["rector.php", "config/rector.php", "/absolute/path/rector.php"]
      }
    }
  }
}
```

**Configuration Precedence:**
1. **Explicit User Setting**: `config_file: "custom/rector.php"` (highest priority)
2. **Auto-Discovery**: Files found in `<projectRoot>/<tool>.php` or `<projectRoot>/config/<tool>.php`
3. **Package Defaults**: `cpsit/quality-tools/config/<tool>.php` (lowest priority)

**User Experience:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      config_file: "custom-rector.php"  # Explicit override
    phpstan:
      # No config_file = auto-discovery + defaults
```

**Auto-Discovery Display:**
```bash
qt config:show
# quality-tools:
#   tools:
#     rector:
#       config_file: "custom-rector.php" (auto-discovered)
#       enabled: true
```

### Implementation Plan

#### Phase 1: Comprehensive Testing Infrastructure (Priority: Critical) - COMPLETED
1. **[x] Build Configuration Test Infrastructure** - Completed
   - [x] Integration test with dataProvider pattern demonstrating Issue 022 behavior
   - [x] Static fixture files in `tests/Fixtures/configFileReplacement/` for multiple test scenarios
   - [x] `tests/Support/ConfigurationBuilder.php` - Test configuration builders
   - [x] `tests/Support/ConfigurationAssertions.php` - Specialized assertions

2. **[x] Create Comprehensive Test Coverage** - Completed
   - [x] `tests/Unit/Configuration/ConfigurationFileValidationTest.php` - Validates config file syntax across tools
   - [x] `tests/Unit/Configuration/CustomConfigSchemaTest.php` - Documents Issue 022 schema validation conflicts
   - [x] `tests/Integration/Configuration/CustomToolConfigurationTest.php` - End-to-end config replacement with dataProvider
   - [x] `tests/Integration/Configuration/ConfigurationRegressionTest.php` - Comprehensive regression protection matrix

3. **[x] Add Edge Case and Error Testing** - Completed
   - [x] `tests/Unit/Configuration/ConfigurationEdgeCaseTest.php` covers file permissions, concurrent access, invalid formats
   - [x] Security boundary validation prevents directory traversal attacks
   - [x] Performance impact measurement ensures loading remains under 100ms
   - [x] Memory usage validation keeps overhead under 1MB

#### Phase 2: Enhanced Schema and Validation (Priority: Critical) - COMPLETED
1. **[x] Update JSON Schema** (`config/schema/quality-tools.json`) - Completed
   - Added `config_file` property to all tool configurations (rector, phpstan, fractor, php-cs-fixer, typoscript-lint)
   - Schema validation now passes for configurations with custom config files
   - Core Issue 022 schema validation conflict RESOLVED

2. **[x] Resolve ConfigurationDiscovery Metadata Injection** - Completed
   - Metadata injection issue resolved in previous refactoring work
   - `tool_config_file` and `custom_config` properties no longer injected
   - ConfigurationDiscovery now uses proper `config_file` properties from schema

3. **[x] Fix Configuration Structure Validation** - Completed
   - Comprehensive normalization implemented in all configuration loaders
   - Added `normalizeConfigurationStructure()` to HierarchicalConfigurationLoader and ConfigurationLoader
   - Tools section structure properly maintained as associative array (object)
   - However: Underlying JSON schema validation issue discovered (see Issue 2 details above)

4. **[x] Configuration File Validation Logic** - Completed
   - Implemented ToolConfigurationValidationService with comprehensive validation
   - Tool validation trait provides shared functionality across all validators
   - Constructor-based dependency injection ensures proper service integration
   - All tool validators (Rector, PHPStan, Fractor, etc.) now use consistent validation pattern
   - Configuration loading works reliably without schema validation errors

5. **[x] Secure Path Resolution Integration** - Completed
   - Integrated FilesystemService::validateConfigurationPath() for configuration overrides
   - Applied SecurityService path sanitization to custom config file paths
   - Implemented context-aware validation in BaseCommand for tool configs
   - ConfigurationDiscovery now uses secure path resolution for custom config files

   **Completed Integration Points**:
   - ConfigurationDiscovery: Integrated FilesystemService::validateConfigurationPath() with refactoring
   - BaseCommand: Context-aware validation - strict for tools, flexible for generic configs
   - Tool validators: Already secure through ToolValidationTrait and FilesystemService
   - Tool commands: Inherit security from BaseCommand automatically

6. **[ ] Remaining Enhanced Schema and Validation Steps** - Moved to Feature 031
   - Advanced schema validation patterns extracted to Feature 031
   - Security patterns, tool-specific validation, and edge cases are now separate scope
   - Current Issue 022 focuses on integration points and secure path resolution

### Architectural Decisions During Integration

The following architectural decisions were made during the implementation of Phase 2, Step 5. These decisions have been documented as Architecture Decision Records (ADRs) for long-term reference:

1. **[ADR-0001: Context-Aware Security Validation](../../../architecture/0001-context-aware-security-validation.md)**
   - Smart validation distinguishing between tool configs and generic test configs
   - Prevents breaking test scenarios while maintaining production security

2. **[ADR-0002: Security at Entry Points](../../../architecture/0002-security-at-entry-points.md)**
   - Security validation at system boundaries rather than throughout codebase
   - Reduces complexity while ensuring consistent enforcement

3. **[ADR-0003: Code Duplication Elimination Through Refactoring](../../../architecture/0003-code-duplication-elimination-through-refactoring.md)**
   - Eliminated duplicate code between loadPhpFile() and loadNeonFile()
   - Improves maintainability and ensures consistent behavior

4. **[ADR-0004: Inheritance-Based Security Propagation](../../../architecture/0004-inheritance-based-security-propagation.md)**
   - Leverages existing class hierarchy for automatic security
   - No modifications needed to individual tool commands

#### Phase 3: Configuration Resolution Logic (Priority: High) - COMPLETED
1. **[x] Enhanced Configuration Discovery** - Fully Implemented

   **Current Status**: Auto-discovery of custom tool configuration files is fully implemented and working. Tools now correctly detect and use custom `rector.php`, `phpstan.neon` files from project root and config/ directories.

   **Test Coverage**: All tests passing in `CustomToolConfigurationTest.php`:
   - 13 test scenarios documenting expected behavior
   - All tests now PASS, confirming auto-discovery is working correctly
   - Tests verify: root discovery, config/ directory discovery, precedence rules, override behavior

   **Completed Tasks for ConfigurationDiscovery**:
   - [x] Implement auto-discovery of tool config files in standard locations (project root, config/ directory)
   - [x] Add secure path resolution with boundary validation via FilesystemService
   - [x] Populate `config_file` keys in tool configurations when custom files are discovered
   - [x] Implement configuration precedence: User-specified > Auto-discovered > Package defaults
   - [x] Integration with ToolConfigurationValidationService for discovered file validation
   - [x] Handle tool-specific file patterns (rector.php, phpstan.neon, fractor.php, etc.)

2. **[x] Enhanced Error Reporting** - Fully Implemented

   **Completed Tasks**:
   - [x] Clear error messages for missing config files using ErrorFactory
   - [x] Debug information for configuration discovery process (verbose output)
   - [x] Tool-specific validation errors where needed (generic errors use generic messages)
   - [x] Detailed troubleshooting guidance in error messages

   **Additional Improvements**:
   - [x] All tool commands now explicitly implement ToolCommandInterface
   - [x] ComposerFixCommand and ComposerLintCommand refactored to extend AbstractToolCommand
   - [x] Consistent error handling across all tool commands

#### Phase 4: Tool Integration and Commands (Priority: High) - COMPLETED
1. **[x] Tool Executor Integration** - Fully Implemented

   **Completed Tasks**:
   - [x] All tool commands use new configuration resolution via AbstractToolCommand
   - [x] Auto-discovery works for all tools (rector, phpstan, fractor, php-cs-fixer, typoscript-lint)
   - [x] Fallback behavior when custom configs are invalid (falls back to package defaults)
   - [x] Configuration precedence working correctly: --config > auto-discovered > package defaults

   **Verified Behavior**:
   - Tools auto-discover config files from project root and config/ directory
   - Verbose mode shows discovery process
   - Error handling provides clear messages and troubleshooting guidance
   - All integration tests pass

2. **[x] Command Enhancement** - Fully Complete

   **Completed Functionality**:
   - [x] config:validate correctly validates with custom configs present
   - [x] config:show displays resolved configuration with all sources
   - [x] Verbose mode shows configuration sources including auto-discovered files
   - [x] Clear warnings when tool configs have validation issues
   - [x] JSON format outputs pure JSON without non-JSON content

   **Completed Test Updates**:
   - [x] Updated ConfigValidateCommandTest with comprehensive custom config scenarios using dataProvider pattern
   - [x] Updated ConfigShowCommandTest with auto-discovery indicators and physical fixtures
   - [x] Enhanced user feedback for configuration source information in tests
   - [x] Fixed all fixture paths and added missing .quality-tools.yaml files to fixtures
   - [x] Refactored ComposerFixCommandTest and ComposerLintCommandTest to use CommandTester
   - [x] All 51 tests in ConfigValidateCommandTest and ConfigShowCommandTest passing with 0 skipped
   - [x] Fixed YamlConfigurationWorkflowTest integration test for JSON output behavior

#### Phase 5: Documentation (Priority: Medium) - COMPLETED
1. **[x] User Guide Updates** (`docs/user-guide/configuration.md`) - Completed
   - [x] Added "Custom Tool Configuration Files" section with config_file examples
   - [x] Documented configuration precedence rules clearly
   - [x] Included step-by-step examples for each tool

2. **[x] Tool-Specific Documentation** - Completed
   - [x] Added `config_file` examples to yaml-configuration.md tool guide
   - [x] Updated troubleshooting guide with Section 8 for configuration scenarios
   - [x] Documented security considerations for custom config files
   - [x] Updated configuration reference.md with config_file properties
   - [x] Updated README.md with Custom Tool Configs and Auto-Discovery features
   - [x] Updated user-guide/index.md for consistency

#### Phase 6: Integration Validation (Priority: High) - PENDING
1. **[ ] Comprehensive Integration Testing** - Pending
   - Test with real-world project structures, including custom tool configurations
   - Test with various configurations including custom tool paths,

2. **[ ] Regression Protection** - Pending
   - Complete regression test matrix for all tool commands
   - Validation of existing behavior preservation
   - Error recovery and user feedback quality assurance

### Success Criteria
- [x] All test suites pass without regression (Phase 1-2 complete, no new failures)
- [x] Custom tool config files validate successfully with proper error messages (Schema and validation complete)
- [x] `qt config:validate` reports accurate validation status (Phase 4 complete - validation working)
- [x] `qt config:show` displays configuration with auto-discovery indicators (Phase 4 complete - verbose mode shows sources)
- [x] Tool commands use custom configuration files when present (Phase 3 Step 1 complete)
- [x] Configuration file validation prevents invalid configurations (Validation service complete)
- [x] Secure path resolution prevents security vulnerabilities (Security integration complete)
- [x] Documentation provides clear examples and troubleshooting guidance (Phase 5 complete - comprehensive docs added)
- [x] Performance impact is minimal and measured (Test infrastructure validates <100ms)
- [x] Edge cases and error conditions are handled gracefully (Edge case tests complete)

### Risk Mitigation
- **Test-First Approach**: Comprehensive testing before implementation prevents regressions
- **Schema Changes**: Maintain strict backward compatibility
- **Security**: Implement secure path resolution with boundary validation
- **Performance**: Monitor and optimize configuration discovery overhead
- **Tool Integration**: Systematic testing of all tool command integrations

### Estimated Effort
- **Phase 1**: 2 days (test infrastructure and comprehensive coverage)
- **Phase 2**: 2 days (schema, validation, and security implementation)
- **Phase 3**: 1-2 days (configuration resolution logic)
- **Phase 4**: 1-2 days (tool integration and command updates)
- **Phase 5**: 1 day (documentation updates)
- **Phase 6**: 1 day (integration validation and final testing)

**Total**: 6-8 days for robust implementation with comprehensive validation

## Current Investigation Findings

### Core Issue 022 Resolution - COMPLETED
**Achievement**: Schema now supports `config_file` properties for all tools (rector, phpstan, fractor, php-cs-fixer, typoscript-lint).

**Validation Results**:
- Unit tests (15/15): All CustomConfigSchemaTest scenarios pass
- Schema validation: User-specified `config_file` properties validate successfully
- User impact: Core Issue 022 resolved - users can specify config_file without validation errors

### Additional Issues Discovered During Implementation

**Issue 1: ConfigurationDiscovery Metadata Injection**
- Location: `src/Configuration/ConfigurationDiscovery.php:165,178`
- Problem: `tool_config_file` and `custom_config` properties still injected but undefined in schema
- Integration test errors: "The property tool_config_file is not defined and the definition does not allow additional properties"

**Issue 2: Configuration Structure Type Mismatch**
- Integration test errors: "Wrong type for quality-tools.tools: Array value found, but an object is required"
- Root cause investigation findings:
  - PHP data structure is correct: `is_list: false` with proper associative array structure
  - Tools section contains expected tool configurations (rector, phpstan, fractor, etc.)
  - `normalizeConfigurationStructure()` implemented in all configuration loaders
  - Normalization method properly called and executes without converting indexed arrays
- **Discovery**: Issue appears to be in JSON schema validation process itself, not PHP data structure
- **Deep Analysis**: Data structure is perfect at PHP level but validator reports array/object mismatch
- **Possible causes**:
  - JSON schema validation library handling of empty associative arrays
  - Serialization between PHP arrays and JSON schema validation
  - Schema validator configuration issue
- Impact: Prevents successful configuration loading despite correct data structure
- Status: **Structural fixes implemented, underlying validation issue requires further investigation**

**Issue 3: Test Infrastructure Validation**
- Fixed: Updated all test expectations to reflect that Issue 022 core problem is resolved
- Updated: Changed `expects_schema_failure: true` to `false` for scenarios with config_file properties
- Result: Unit tests now pass, integration tests reveal deeper structural issues

**Issue 4: Custom Configuration Files Not Used**
- **Symptom**: `config:validate` and `config:show` commands now succeed, but custom config files are ignored
- **Observed Behavior**: When `rector.php` exists in project root, tool execution uses default configuration with `quality-tools.paths.scan` paths instead of custom file paths
- **Root Cause**: Configuration discovery and validation succeed, but tool execution still uses default configurations
- **Impact**: Users see "valid" configuration but tools don't use their custom settings
- **Status**: Configuration file detection works, but execution integration is missing
- **Next Steps**: Implement Phase 3 Configuration Resolution Logic and Phase 4 Tool Integration

### Architecture Decision Required
Two approaches for handling discovery metadata:
1. **Schema Addition**: Add `tool_config_file` and `custom_config` to schema (maintains current architecture)
2. **Metadata Removal**: Refactor to avoid runtime metadata injection (cleaner architecture, more work)

**Recommendation**: Approach 2 for cleaner long-term architecture.
