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

## Next Steps
1. Update JSON schema to allow tool configuration metadata
2. Fix configuration discovery to properly handle custom tool configs
3. Ensure tool executors respect custom configuration files
4. Add comprehensive test coverage for configuration override scenarios
5. Update documentation with configuration override examples

## Investigation Notes
- The loadPhpFile() and loadNeonFile() methods in ConfigurationDiscovery intentionally add metadata keys not defined in schema
- This appears to be a design oversight where configuration discovery was implemented without corresponding schema updates
- The issue affects all tools that support custom configuration files (rector, phpstan, fractor, php-cs-fixer, typoscript-lint)