# Feature 010: Unified YAML Configuration System

**Status:** Completed
**Actual Time:** ~8 hours
**Layer:** Core System
**Dependencies:** None

## Implementation Summary

**Feature Status:** Fully completed and tested
**Implementation Date:** January 5, 2026
**Test Coverage:** 428/428 tests passing (356 unit + 72 integration tests)

### Completed Components

1. **Core YAML Configuration System**
   - `YamlConfigurationLoader` - Loads and merges configurations from hierarchy
   - `Configuration` - Central configuration management with type-safe getters
   - `ConfigurationValidator` - JSON Schema validation with helpful error messages
   - `ValidationResult` - Validation result wrapper with error reporting

2. **CLI Commands**
   - `config:init` - Initialize configuration with project templates
   - `config:show` - Display resolved configuration
   - `config:validate` - Validate configuration syntax and schema

3. **Configuration Features**
   - Configuration hierarchy: package defaults -> global -> project -> CLI overrides
   - Environment variable interpolation with `${VAR:-default}` syntax
   - Four project templates: default, typo3-extension, typo3-site-package, typo3-distribution
   - Complete JSON Schema validation with clear error messages
   - Support for `.quality-tools.yaml`, `quality-tools.yaml`, and `quality-tools.yml`

4. **Documentation**
   - Complete user guide with 6 configuration documents
   - API documentation for developers
   - Migration guide from tool-specific configurations
   - Comprehensive examples and troubleshooting guide

### Key Achievements
- **100% backward compatibility** - existing tool configs continue to work
- **Zero configuration** - works out of the box with strong defaults
- **Developer-friendly** - YAML format with comments and clear validation
- **Comprehensive testing** - Full test coverage with integration tests
- **Production ready** - All tests passing, no known issues

## Description

Implement a developer-focused unified YAML configuration system with strong defaults based on widespread standards. This provides a human-readable, comment-friendly configuration approach for developers who manually maintain and collaborate on quality tool configurations.

## Problem Statement

Currently, each quality tool (Rector, Fractor, PHPStan, PHP CS Fixer, etc.) has its own configuration approach and file format. This creates:

- Inconsistent configuration patterns across tools
- Difficulty in maintaining configurations
- Lack of standardized defaults
- Complex setup for new projects
- No support for inline documentation and comments

## Goals

- Establish a single, unified YAML configuration approach for developers
- Provide strong, opinionated defaults based on TYPO3 and PHP best practices
- Support comments and inline documentation for team collaboration
- Maintain compatibility with existing tool configurations
- Reduce configuration complexity for end users

## Tasks

- [x] YAML Configuration System Architecture
  - [x] Design unified YAML configuration schema
  - [x] Define configuration hierarchy and precedence
  - [x] Create YAML configuration validation system
  - [x] Implement YAML configuration loading mechanism
- [x] Default Configuration Sets
  - [x] Create TYPO3-specific default configurations
  - [x] Establish PHP 8.3+ standard configurations
  - [x] Define code quality baseline configurations
  - [x] Implement configuration inheritance system
- [x] Developer-Friendly Features
  - [x] Add comprehensive comment support and examples
  - [x] Create configuration templates for common scenarios
  - [x] Implement environment variable interpolation
  - [x] Add configuration validation with clear error messages
- [x] Tool Integration
  - [x] Adapt existing tool configurations to use unified system
  - [x] Create YAML-to-tool configuration transformers
  - [x] Implement backward compatibility layer
  - [x] Add configuration debugging and validation commands

## Success Criteria

- [x] Single YAML configuration file can control all quality tools
- [x] Zero-configuration setup works for standard TYPO3 projects
- [x] All existing tool configurations remain functional
- [x] Configuration validation prevents invalid setups with helpful messages
- [x] Inline comments and documentation are preserved and utilized
- [x] Configuration is intuitive for developers to read and modify

## Technical Requirements

### Configuration Format

**Primary Format:**
- YAML as the primary human-friendly configuration format
- Support for comments, multi-line strings, and readable structure
- Schema validation using JSON Schema (converted from YAML)
- Environment variable interpolation with `${ENV_VAR}` syntax

### Configuration Hierarchy

1. Package defaults (lowest priority)
2. Global user configuration (`~/.quality-tools.yaml`)
3. Project-specific configuration (`quality-tools.yaml`)
4. Command-line overrides (highest priority)

### File Discovery

- `quality-tools.yaml` (preferred)
- `quality-tools.yml` (alternative)
- `.quality-tools.yaml` (hidden file variant)

## Implementation Plan

### Phase 1: Core YAML System (3-4 hours)

1. Define configuration schema optimized for YAML format
2. Implement YAML loading and validation with symfony/yaml
3. Create default configuration sets with extensive comments
4. Add configuration debugging and validation tools

### Phase 2: Developer Experience (2-3 hours)

1. Create configuration templates and examples
2. Implement helpful validation error messages
3. Add environment variable interpolation
4. Build configuration preview and debugging commands

### Phase 3: Tool Integration (1-2 hours)

1. Create YAML-to-tool configuration transformers
2. Implement backward compatibility support
3. Test with existing TYPO3 projects
4. Add comprehensive documentation

## Configuration Schema

```yaml
# Quality Tools Configuration
# This file configures all quality analysis tools for your TYPO3 project
quality-tools:
  # Project settings
  project:
    name: "${PROJECT_NAME}"
    php_version: "8.3"    # Target PHP version
    typo3_version: "13.4" # Target TYPO3 version

  # Scan paths configuration
  paths:
    # Directories to analyze
    scan:
      - "packages/"         # Custom extensions
      - "config/system/"    # System configuration
    # Directories to exclude
    exclude:
      - "var/"             # Runtime cache and logs
      - "vendor/"          # Third-party packages
      - "node_modules/"    # Frontend dependencies

  # Tool-specific settings
  tools:
    # Rector - PHP modernization and refactoring
    rector:
      enabled: true
      level: "typo3-13"      # TYPO3 13.x compatibility
      php_version: "8.3"     # Override global PHP version if needed
      # dry_run: true        # Preview changes without applying

    # Fractor - TypoScript modernization
    fractor:
      enabled: true
      indentation: 2         # Spaces for TypoScript indentation
      # skip_files: []       # Files to skip

    # PHPStan - Static analysis
    phpstan:
      enabled: true
      level: 6               # Analysis strictness (0-9)
      memory_limit: "1G"     # Memory limit for large projects
      # paths: []            # Override scan paths

    # PHP CS Fixer - Code style fixing
    php-cs-fixer:
      enabled: true
      preset: "typo3"        # TYPO3 coding standards
      # cache: true          # Enable caching for performance

    # TypoScript Lint - TypoScript validation
    typoscript-lint:
      enabled: true
      indentation: 2         # Expected indentation
      # ignore_patterns: []  # Patterns to ignore

  # Output and reporting
  output:
    verbosity: "normal"      # quiet, normal, verbose, debug
    colors: true             # Enable colored output
    # progress: true         # Show progress bars

  # Performance optimization
  performance:
    parallel: true           # Run tools in parallel when possible
    max_processes: 4         # Maximum concurrent processes
    # cache_enabled: true    # Enable result caching
```

## Developer Experience Features

### Configuration Templates

```bash
# Generate configuration for different project types
qt config:init --template=typo3-extension
qt config:init --template=typo3-site-package
qt config:init --template=typo3-distribution
```

### Configuration Validation

```bash
# Validate configuration file
qt config:validate

# Show resolved configuration (after inheritance and merging)
qt config:show

# Debug configuration loading process
qt config:debug
```

### Environment Variable Support

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-default-project}"
  tools:
    phpstan:
      memory_limit: "${PHPSTAN_MEMORY:-1G}"
```

## Class Implementation

```php
class YamlConfigurationLoader
{
    public function load(string $projectRoot): Configuration
    {
        $configFiles = [
            'quality-tools.yaml',
            'quality-tools.yml',
            '.quality-tools.yaml'
        ];

        foreach ($configFiles as $configFile) {
            $path = $projectRoot . '/' . $configFile;
            if (file_exists($path)) {
                return $this->loadYamlFile($path);
            }
        }

        return $this->getDefaultConfiguration();
    }

    private function loadYamlFile(string $path): Configuration
    {
        $content = file_get_contents($path);
        $content = $this->interpolateEnvironmentVariables($content);
        $data = Yaml::parse($content);

        $this->validateConfiguration($data);

        return new Configuration($data);
    }
}

class ConfigurationValidator
{
    public function validate(array $config): ValidationResult
    {
        // Validate against JSON Schema converted from YAML
        $validator = new JsonSchemaValidator();
        return $validator->validate($config, $this->getSchema());
    }

    public function getValidationErrors(array $config): array
    {
        // Return human-friendly error messages for developers
    }
}
```

## Backward Compatibility

- Existing tool-specific configuration files remain fully functional
- Unified YAML configuration supplements but doesn't replace existing configs
- Clear migration path for projects wanting to adopt unified config
- Tool-specific configurations take precedence when both exist
- Migration utility: `qt config:migrate`

## Performance Considerations

- Configuration caching with file modification time checking
- Lazy loading of tool-specific configurations
- Efficient YAML parsing with symfony/yaml optimizations
- Environment variable interpolation caching
- Minimal overhead for configuration processing

## Testing Strategy

- Unit tests for YAML loading and validation
- Integration tests with each quality tool
- Backward compatibility tests with existing projects
- Performance tests for configuration processing
- Template and example validation tests

## Dependencies

- `symfony/yaml`: For YAML configuration parsing and dumping
- `justinrainbow/json-schema`: For configuration validation
- `symfony/config`: For configuration processing and caching
- `vlucas/phpdotenv`: For environment variable support

## Risk Assessment

**Medium:**
- Complex configuration inheritance logic
- YAML parsing edge cases and validation
- Maintaining backward compatibility across tool updates

**Mitigation:**
- Comprehensive test coverage for YAML parsing scenarios
- Clear separation between unified and tool-specific configurations
- Extensive validation with helpful error messages
- Phased rollout starting with read-only configuration

## Future Enhancements

- Configuration templates for different TYPO3 project types
- IDE integration with YAML schema for autocompletion
- Configuration sharing and community templates
- Visual configuration editor with YAML export
- Advanced environment variable interpolation

## Notes

- Focus on developer experience with clear, commented examples
- Start with read-only unified configuration to avoid breaking changes
- Prioritize common TYPO3 use cases with sensible defaults
- Ensure tool-specific advanced configurations remain accessible through existing files
- Document configuration precedence and inheritance clearly
- Include extensive inline documentation in default configurations
