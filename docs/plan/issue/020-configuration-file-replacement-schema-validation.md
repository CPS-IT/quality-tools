# Issue 020: Configuration File Replacement Schema Validation Bug

## Status
**Open** - Identified during refactoring work on issue 019

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
- Configuration override test scenarios (documented in tmp/configuration-override-test-scenarios.md)

## Implementation Plan

### Analysis Results

#### 1. Test Coverage Assessment
**Current State:** 
- **MAJOR GAP**: Zero test coverage for custom configuration file replacement scenarios
- No tests for `tool_config_file` and `custom_config` metadata keys 
- ConfigValidateCommand and ConfigShowCommand tests exist but don't cover custom tool configs
- Tool command tests focus on default configurations only

**Impact:** Critical functionality untested, explaining why the bug went undetected.

#### 2. Test Cases Definition
**Required Test Scenarios:**

**Schema Validation Tests:**
- Custom tool config files should validate successfully 
- Merged configuration with tool metadata should pass validation
- Schema should reject invalid tool_config_file paths
- Schema should handle multiple custom tool configs

**Configuration Discovery Tests:**
- Detect custom rector.php, phpstan.neon, fractor.php files
- Handle precedence: custom files override YAML configuration
- Support both project root and config/ directory placement
- Validate tool-specific configuration loading

**Command Integration Tests:**
- `qt config:validate` should report valid for custom configs
- `qt config:show` should display merged config without errors
- `qt lint:*` commands should use custom configuration files
- Tool execution should respect custom config precedence

#### 3. Documentation Assessment
**Current State:** 
- Configuration override concept documented in feature/015 plan
- User guide mentions "custom configuration" but lacks specific examples
- Tool-specific docs mention config files but not replacement behavior
- **MISSING**: Step-by-step custom config file setup examples

**Required Updates:**
- Add custom tool config file examples to user guide
- Document configuration precedence rules clearly
- Update troubleshooting guide with validation scenarios

### Implementation Phases

#### Phase 1: Schema and Validation Fix (Priority: Critical)
1. **Update JSON Schema** (`config/schema/quality-tools.json`)
   - Add `tool_config_file` and `custom_config` properties to quality-tools section
   - Define validation rules for tool config file paths
   - Ensure backward compatibility with existing configurations

2. **Fix Configuration Validation** (`src/Configuration/ConfigurationValidator.php`)
   - Handle tool metadata keys in validation process
   - Ensure merged configurations validate correctly
   - Add specific error messages for tool config issues

#### Phase 2: Test Implementation (Priority: High)
3. **Create Configuration File Replacement Test Suite**
   - `tests/Integration/Configuration/CustomToolConfigTest.php`
   - Test all supported custom config files (rector.php, phpstan.neon, etc.)
   - Validate configuration discovery and precedence rules
   - Test schema validation for custom configs

4. **Extend Command Integration Tests**
   - Update `ConfigValidateCommandTest` with custom config scenarios
   - Update `ConfigShowCommandTest` with merged configuration display
   - Add tool command tests with custom configuration files
   - Test error scenarios and edge cases

5. **Add Schema Validation Tests**
   - `tests/Unit/Configuration/CustomConfigSchemaTest.php`
   - Test schema validation for tool metadata keys
   - Validate error handling for invalid tool config paths
   - Test schema evolution and backward compatibility

#### Phase 3: Configuration Discovery Enhancement (Priority: Medium)
6. **Enhance ConfigurationDiscovery**
   - Ensure proper validation of discovered tool config files
   - Improve error reporting for invalid custom configurations
   - Add debug information for tool config detection

7. **Tool Executor Integration**
   - Verify tool commands properly use custom configuration files
   - Ensure fallback behavior when custom configs are invalid
   - Test configuration precedence in tool execution

#### Phase 4: Documentation Update (Priority: Medium) 
8. **Update User Guide** (`docs/user-guide/configuration.md`)
   - Add "Custom Tool Configuration Files" section
   - Include step-by-step examples for each tool
   - Document configuration precedence rules

9. **Update Tool-Specific Documentation**
   - Add custom config examples to Rector, PHPStan, Fractor docs
   - Document troubleshooting for configuration issues
   - Add migration guide from default to custom configs

#### Phase 5: Quality Assurance (Priority: High)
10. **Regression Testing**
    - Run full test suite to ensure no breaking changes
    - Test backward compatibility with existing projects
    - Validate performance impact of schema changes

11. **Integration Validation**
    - Test with real-world project scenarios
    - Validate configuration hierarchy behavior
    - Test error recovery and user feedback

### Success Criteria
- [ ] All test suites pass without regression
- [ ] Custom tool config files validate successfully 
- [ ] `qt config:validate` reports accurate validation status
- [ ] `qt config:show` displays merged configuration correctly
- [ ] Tool commands use custom configuration files when present
- [ ] Documentation provides clear guidance for custom config setup
- [ ] Schema validation provides helpful error messages

### Risk Mitigation
- **Schema Changes**: Maintain backward compatibility with existing configs
- **Test Coverage**: Implement comprehensive test scenarios before fixing code
- **Documentation**: Update docs alongside implementation to prevent user confusion
- **Performance**: Monitor validation performance impact with custom configs

### Estimated Effort
- **Phase 1 & 2**: 2-3 days (critical path)
- **Phase 3 & 4**: 1-2 days (parallel with testing)
- **Phase 5**: 1 day (validation and cleanup)

**Total**: 4-6 days for complete implementation and validation

## Investigation Notes
- The loadPhpFile() and loadNeonFile() methods in ConfigurationDiscovery intentionally add metadata keys not defined in schema
- This appears to be a design oversight where configuration discovery was implemented without corresponding schema updates
- The issue affects all tools that support custom configuration files (rector, phpstan, fractor, php-cs-fixer, typoscript-lint)