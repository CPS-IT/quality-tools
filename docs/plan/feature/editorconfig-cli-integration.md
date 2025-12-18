# Feature: EditorConfig CLI Integration

**Status:** Not Started  
**Estimated Time:** 6-8 hours  
**Layer:** MVP  
**Dependencies:** unified-configuration-system (Not Started), vendor-folder-derivation (Not Started)

## Description

Integrate EditorConfig CLI tool for validating and fixing file formatting according to .editorconfig rules. This includes implementing `qt lint:ec` and `qt fix:ec` commands with proper configuration management and template generation.

## Problem Statement

EditorConfig CLI requires specific setup and configuration management:

- Configuration file must be in project root
- No current integration with other quality tools
- Manual setup required for each project
- Inconsistent usage patterns across projects
- Limited configuration template and standardization

## Goals

- Integrate EditorConfig CLI with unified quality tools system
- Provide `qt lint:ec` command for validation
- Provide `qt fix:ec` command for automatic fixing
- Generate .editorconfig from templates when needed
- Support project-specific EditorConfig customization

## Tasks

- [ ] EditorConfig CLI Integration
  - [ ] Add EditorConfig CLI as dependency
  - [ ] Implement `qt lint:ec` command for validation
  - [ ] Implement `qt fix:ec` command for automatic fixing
  - [ ] Create proper error handling and reporting
- [ ] Configuration Management
  - [ ] Create .editorconfig template for TYPO3 projects
  - [ ] Implement template generation and customization
  - [ ] Add project root detection for config placement
  - [ ] Support existing .editorconfig files
- [ ] Template System
  - [ ] Design flexible .editorconfig template system
  - [ ] Create TYPO3-specific default templates
  - [ ] Add template customization and overrides
  - [ ] Implement template validation and verification
- [ ] Integration with Unified System
  - [ ] Integrate with unified configuration system
  - [ ] Add to `qt lint` and `qt fix` commands
  - [ ] Support unified argument and option handling
  - [ ] Create consistent reporting format

## Success Criteria

- [ ] `qt lint:ec` validates EditorConfig compliance
- [ ] `qt fix:ec` automatically fixes formatting issues
- [ ] .editorconfig templates are generated when missing
- [ ] EditorConfig integration works with unified commands
- [ ] Project-specific EditorConfig customization is supported

## Technical Requirements

### Command Interface

```bash
# Lint EditorConfig compliance
qt lint:ec
qt lint:ec --path src/ --verbose

# Fix EditorConfig issues
qt fix:ec
qt fix:ec --dry-run --path config/

# Generate .editorconfig from template
qt generate:editorconfig
qt generate:editorconfig --template typo3 --force
```

### Template System

- Default TYPO3 .editorconfig template
- Project-specific template customization
- Template inheritance and composition
- Validation of generated configurations

## Implementation Plan

### Phase 1: Basic Integration

1. Add EditorConfig CLI dependency
2. Implement basic `qt lint:ec` and `qt fix:ec` commands
3. Create project root detection for config placement
4. Add basic error handling and reporting

### Phase 2: Template System

1. Create .editorconfig template for TYPO3 projects
2. Implement template generation system
3. Add template customization and override capabilities
4. Create template validation and verification

### Phase 3: Unified Integration

1. Integrate with unified configuration system
2. Add to `qt lint` and `qt fix` commands
3. Implement consistent argument and option handling
4. Create unified reporting format

## Configuration Schema

```yaml
tools:
  editorconfig:
    enabled: true
    
    # Template configuration
    template:
      name: "typo3"  # Default template
      auto_generate: true  # Generate if missing
      force_update: false
      
    # CLI options
    cli_options:
      exclude_patterns:
        - "vendor/*"
        - "var/*"
        - "*.min.*"
      
    # Custom rules (merged with template)
    custom_rules:
      "*.md":
        trim_trailing_whitespace: false
```

## File Structure

```
project-root/
├── .editorconfig              # Generated/managed config
├── .editorconfig.template     # Project-specific template
└── .quality-tools/
    └── templates/
        └── editorconfig/
            ├── typo3.template     # TYPO3 template
            └── custom.template    # Custom template
```

## Default TYPO3 Template

```ini
# .editorconfig template for TYPO3 projects
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true

[*.php]
indent_style = space
indent_size = 4

[*.{ts,typoscript}]
indent_style = space
indent_size = 2

[*.{js,css,scss,less}]
indent_style = space
indent_size = 2

[*.{json,yaml,yml}]
indent_style = space
indent_size = 2

[*.xml]
indent_style = space
indent_size = 2

[*.md]
trim_trailing_whitespace = false

[{composer.json,package.json}]
indent_style = space
indent_size = 2
```

## Performance Considerations

- Efficient file pattern matching for large projects
- Caching of EditorConfig rules and computations
- Optimized scanning for relevant files only
- Memory-efficient processing of large files

## Testing Strategy

- Unit tests for template generation and customization
- Integration tests with EditorConfig CLI
- Validation tests for generated .editorconfig files
- Cross-platform file handling tests
- Performance tests with large projects

## Dependencies

- editorconfig/editorconfig-cli-php: Core EditorConfig CLI functionality
- Template engine for configuration generation

## Risk Assessment

**Low:**
- EditorConfig is well-established with predictable behavior
- File formatting operations are generally safe
- Template generation is read-only until user confirmation

**Mitigation:**
- Comprehensive testing with various file types and patterns
- Backup mechanisms before applying fixes
- Clear error reporting and validation
- Safe defaults in templates and configurations

## Future Enhancements

- Multiple template presets (Laravel, Symfony, etc.)
- IDE integration for EditorConfig validation
- Real-time EditorConfig compliance checking
- Team-based EditorConfig sharing and synchronization
- Advanced rule customization and conditional logic

## Notes

- EditorConfig CLI must be installed and available in PATH or vendor directory
- .editorconfig file location in project root is requirement of EditorConfig standard
- Focus on TYPO3-specific formatting standards in default template
- Consider performance implications for large projects with many files
- Plan for cross-platform file format handling (CRLF vs LF)
