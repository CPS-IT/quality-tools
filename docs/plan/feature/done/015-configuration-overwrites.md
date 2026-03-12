# Feature 015: Configuration Overwrites

- **Status:** Completed
- **Estimated Time:** 6–8 hours
- **Layer:** MVP
- **Dependencies:** 010-unified-yaml-configuration-system (Not Started)

## Description

Implement a hierarchical configuration overwrite system that allows projects to customize quality tool configurations at multiple levels. This provides flexibility while maintaining strong defaults from the unified configuration system.

Developers can use the quality tools without having to modify their existing configuration files.

## Problem Statement

Projects need the ability to customize quality tool configurations for their specific needs without losing the benefits of standardized defaults. Currently, there's no clear precedence order for configuration files, leading to:

- Unpredictable configuration behavior
- Difficulty in understanding which configuration takes effect
- No clear way to override specific settings while keeping others

## Goals

- Establish a clear configuration precedence hierarchy
- Support multiple configuration file locations
- Enable partial configuration overrides
- Maintain backward compatibility with existing configurations

## Tasks

- [x] Configuration Precedence System
  - [x] Design configuration hierarchy and precedence rules
  - [x] Implement configuration file discovery mechanism
  - [x] Create configuration merging algorithm
  - [x] Add configuration source tracking and debugging
- [x] Configuration File Support
  - [x] Support phpcs.xml in the project root
  - [x] Support tool configs in package root directories
  - [x] Support tool configs in config/ subdirectory
  - [x] Support .quality-tools.yaml in various locations
- [x] Override Mechanisms
  - [x] Implement deep configuration merging
  - [x] Add configuration validation after merging
  - [x] Create override conflict detection
  - [x] Add configuration inheritance documentation

## Success Criteria

- [x] Clear, documented configuration precedence order
- [x] Projects can override any configuration setting
- [x] Configuration merging works predictably
- [x] Debugging tools show which config files are active
- [x] Backward compatibility maintained for existing projects

## Technical Requirements

### Configuration File Locations (in precedence order)

1. Command line arguments (the highest priority)
2. `.quality-tools.yaml` in project root
3. `.quality-tools.yaml` in config/ directory
4. Tool-specific config in project root (e.g., `phpcs.xml`)
5. Tool-specific config in an arbitrary directory (e.g., <package>/`config/phpcs.xml`, <package>/`phpcs.xml`, <project>/`config/phpcs.xml`)
6. `.quality-tools.yaml` in package root
7. Package defaults (the lowest priority)

### Configuration Merging Strategy

- Arrays: Merge and deduplicate
- Objects: Deep merge with override
- Scalars: Override completely
- Special handling for path arrays (relative path resolution)
- a custom config file for a tool overrides the default config file and all other configs for that tool in configuration YAML files. (config file set as command argument or in `.quality-tools.yaml`)

## Implementation Plan

### Phase 1: Discovery and Loading

1. Implement configuration file discovery algorithm
2. Create a configuration loading with source tracking
3. Add configuration validation at each level
4. Implement basic merging for simple cases

### Phase 2: Advanced Merging

1. Implement deep configuration merging
2. Add conflict detection and resolution
3. Create debugging and introspection tools
4. Add configuration caching for performance

## Configuration Schema

Extends unified YAML configuration from Feature 010:

```yaml
# Example: project-root/.quality-tools.yaml
quality-tools:
  # Override specific tool settings
  tools:
    rector:
      # Override specific Rector rules
      level: "typo3-12"  # Override from default typo3-13
      skip:
        - "Rector\\TypeDeclaration\\Rector\\ClassMethod\\AddVoidReturnTypeWhereNoReturnRector"

    phpstan:
      # Override PHPStan level for this project
      level: 5  # Override from default 6

  # Override global path configuration
  paths:
    exclude:
      - "packages/third-party/"
      - "var/cache/"
      - "packages/legacy-extension/"
```

## File Structure

```
project-root/
├── .quality-tools.yaml              # Project-level overrides
├── phpcs.xml                      # Legacy PHP CS Fixer config
├── config/
│   ├── .quality-tools.yaml         # Config directory overrides
│   └── rector.php                 # Legacy Rector config
└── packages/
    └── custom-package/
        └── .quality-tools.yaml     # Package-specific overrides
```

## Backward Compatibility

- Existing tool-specific configuration files remain functional
- Legacy configurations take precedence in their respective tools
- Clear migration path from legacy to unified configuration
- Configuration validation warns about conflicts but doesn't break builds

## Performance Considerations

- Configuration file discovery caching
- Lazy loading of configuration files
- Efficient deep merging algorithms
- Configuration result caching per execution context

## Testing Strategy

- Unit tests for configuration discovery and merging
- Integration tests with various file combinations
- Precedence validation tests for all scenarios
- Performance tests for large configuration hierarchies
- Backward compatibility tests with existing projects

## Dependencies

- **Feature 010 (Unified YAML Configuration System)**: Provides base configuration schema and loading infrastructure
- Configuration merging algorithms and validation
- File system discovery and caching mechanisms

## Risk Assessment

**Low:**
- Well-defined precedence rules reduce ambiguity
- Existing functionality remains unchanged
- Configuration validation catches most issues

**Mitigation:**
- Comprehensive test coverage for precedence scenarios
- Clear documentation with examples
- Configuration debugging tools
- Gradual migration path for existing projects

## Future Enhancements

- Environment-specific configuration overrides
- Conditional configuration based on project characteristics
- Configuration templates and inheritance
- IDE integration for configuration validation
- Configuration diff tools for debugging

## Notes

- Precedence rules should be intuitive and well-documented
- Consider the performance impact of multiple configuration files
- Ensure debugging tools make precedence clear to users
- Plan for future extension without breaking changes
- Maintain consistency with the Feature 010 YAML schema structure
