# Feature 017: EditorConfig CLI Integration

**Status:** Not Started
**Estimated Time:** 6–8 hours
**Layer:** to be defined
**Dependencies:** 010-unified-yaml-configuration-system (Completed), 016-fail-on-warnings-configuration (Not Started)

## Description

Integrate EditorConfig CLI as a first-class linting and fixing tool within the quality-tools package, providing consistent file formatting validation and automatic fixes. Include automatic `.editorconfig` template provisioning for projects without existing configuration.

## Problem Statement

Currently, EditorConfig validation is handled outside the quality-tools ecosystem:
- e.g. `composer lint:editorconfig` and `composer fix:editorconfig` use direct EditorConfig CLI calls
- No integration with quality-tools YAML configuration system
- No multi-path scanning support
- Missing automatic `.editorconfig` template provisioning
- Inconsistent exit code behavior for CI/CD pipelines
- No unified reporting or aggregated metrics

## Goals

- **First-class integration**: Add `qt lint:editorconfig` and `qt fix:editorconfig` commands
- **Multi-path scanning**: Apply EditorConfig validation across all configured paths
- **Template provisioning**: Automatically provide `.editorconfig` templates for new projects
- **YAML configuration**: Full integration with quality-tools configuration system
- **CI/CD reliability**: Consistent exit code behavior with warning detection
- **Unified experience**: Same command patterns and output as other quality tools

## Tasks

- [ ] **EditorConfig Command Implementation**
  - [ ] Create `EditorConfigLintCommand.php` extending BaseCommand
  - [ ] Create `EditorConfigFixCommand.php` extending BaseCommand
  - [ ] Register commands in QualityToolsApplication
  - [ ] Add command help text and descriptions
- [ ] **Configuration Schema Extension**
  - [ ] Add `editorconfig` tool configuration to YAML schema
  - [ ] Support file pattern configuration (*.css, *.js, *.html, *.php, etc.)
  - [ ] Add tool-specific options (check-only patterns, exclusions)
  - [ ] Update ConfigurationValidator.php with editorconfig validation
- [ ] **EditorConfig Template System**
  - [ ] Create default `.editorconfig` template in `config/templates/`
  - [ ] Implement template detection and provisioning logic
  - [ ] Add automatic template copying during `config:init`
  - [ ] Add template copying when no `.editorconfig` found during execution
- [ ] **Multi-Path Support**
  - [ ] Implement EditorConfig scanning across resolved paths
  - [ ] Add support for package-specific `.editorconfig` files
  - [ ] Handle nested `.editorconfig` inheritance properly
  - [ ] Validate file patterns against resolved paths
- [ ] **Integration with Warning Detection**
  - [ ] Add EditorConfig output parsing for warning detection
  - [ ] Implement pattern matching for EditorConfig violations
  - [ ] Ensure proper exit codes with fail-on-warnings behavior
  - [ ] Add EditorConfig-specific error handling
- [ ] **Testing and Documentation**
  - [ ] Add unit tests for EditorConfig commands
  - [ ] Add integration tests with template provisioning
  - [ ] Test multi-path scanning functionality
  - [ ] Update configuration documentation with EditorConfig examples

## Success Criteria

- [ ] `qt lint:editorconfig` and `qt fix:editorconfig` commands work correctly
- [ ] EditorConfig validation runs across all configured paths
- [ ] `.editorconfig` templates are automatically provided when missing
- [ ] Configuration integrates seamlessly with existing YAML structure
- [ ] Exit codes behave consistently with other linting tools
- [ ] Multi-path scanning covers all project files according to patterns
- [ ] Template system works for both `config:init` and automatic provisioning
- [ ] Comprehensive test coverage for all EditorConfig functionality

## Technical Requirements

### EditorConfig Tool Integration
- Detect and use existing EditorConfig CLI installation
- Support both local (`node_modules/.bin/ec`) and global EditorConfig installations
- Handle EditorConfig CLI argument formatting and output parsing
- Provide clear error messages when EditorConfig CLI is not available

### Template System
- Store default `.editorconfig` template suitable for TYPO3 projects
- Detect missing `.editorconfig` files in project root and package directories
- Prompt user before copying templates (configurable auto-copy behavior)
- Support template customization through configuration

### Multi-Path Processing
- Apply EditorConfig validation to all files matching configured patterns
- Respect existing `.editorconfig` hierarchies and inheritance rules
- Handle package-specific overrides and configurations
- Provide aggregated reporting across all scanned paths

### Configuration Integration
```yaml
quality-tools:
  tools:
    editorconfig:
      enabled: true
      patterns:
        - "*.css"
        - "*.js"
        - "*.html"
        - "*.php"
        - "*.ts"
        - "*.scss"
      excludePatterns:
        - "*.min.js"
        - "*.min.css"
      autoProvisionTemplate: true  # Auto-copy template when missing
      templateSource: "typo3"      # Template variant (typo3, generic)
      tolerateWarnings: false      # Inherited from global setting
```

## Implementation Plan

### Phase 1: Core Command Structure (2-3 hours)
1. Implement `EditorConfigLintCommand` and `EditorConfigFixCommand` classes
2. Add basic EditorConfig CLI integration and process execution
3. Register commands and add basic configuration schema
4. Create unit tests for command structure

### Phase 2: Template System (2-3 hours)
1. Create default `.editorconfig` template for TYPO3 projects
2. Implement template detection and provisioning logic
3. Add integration with `config:init` command
4. Add automatic template copying during command execution

### Phase 3: Multi-Path Integration (2 hours)
1. Integrate with existing path resolution system
2. Implement file pattern matching across resolved paths
3. Add aggregated reporting and metrics collection
4. Test with complex multi-package project structures

## Configuration Schema

```yaml
quality-tools:
  tools:
    editorconfig:
      enabled: true
      patterns:
        - "*.css"
        - "*.js"
        - "*.html"
        - "*.php"
        - "*.yaml"
        - "*.yml"
        - "*.ts"
        - "*.scss"
      excludePatterns:
        - "*.min.js"
        - "*.min.css"
        - "vendor/**"
      autoProvisionTemplate: true
      templateSource: "typo3"  # Options: typo3, generic, custom
      customTemplatePath: null # Path to custom template file
```

## File Structure

```
src/
└── Console/Command/
    ├── EditorConfigLintCommand.php    # New lint command
    └── EditorConfigFixCommand.php     # New fix command
config/
└── templates/
    ├── .editorconfig-typo3           # TYPO3-optimized template
    ├── .editorconfig-generic         # Generic template
    └── README.md                     # Template documentation
tests/
├── Unit/Console/Command/
│   ├── EditorConfigLintCommandTest.php
│   └── EditorConfigFixCommandTest.php
└── Integration/
    └── EditorConfigTemplateTest.php  # Template provisioning tests
```

## EditorConfig Template Content

The TYPO3-optimized template will include:

```ini
# EditorConfig configuration for TYPO3 projects
# https://editorconfig.org/

root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 4

[*.{yaml,yml}]
indent_size = 2

[*.{ts,typoscript}]
indent_size = 2

[*.{js,css,scss,html}]
indent_size = 2

[*.json]
indent_size = 2

[{composer.json,.editorconfig}]
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```

## Integration with Existing Commands

Update the following commands to trigger EditorConfig template provisioning:
- `config:init` - Always offer to create `.editorconfig` from template
- First-time execution of `qt lint:editorconfig` - Prompt for template creation
- `qt fix:editorconfig` - Auto-provision template if `autoProvisionTemplate: true`

## Backward Compatibility

- Existing `composer lint:editorconfig` commands continue working
- Migration path provided through documentation
- No breaking changes to existing `.editorconfig` files
- Template system is opt-in by default (configurable)

## Dependencies

- **EditorConfig CLI**: Requires `editorconfig-cli` to be installed locally or globally
- **Node.js ecosystem**: EditorConfig CLI is typically installed via npm
- **File system access**: Template copying requires write permissions to project root

## Risk Assessment

**Low Risk:**
- EditorConfig CLI is well-established and stable
- Template system is additive and doesn't modify existing files
- Integration follows established patterns from other tools

**Mitigation:**
- Clear error messages when EditorConfig CLI is not available
- Fallback behavior when template provisioning fails
- Comprehensive testing of template system edge cases

## Future Enhancements

- Support for custom template repositories
- Template versioning and update notifications
- Integration with project scaffolding tools
- Advanced file pattern matching with custom rules
- EditorConfig validation reporting and metrics

## Notes

- EditorConfig CLI installation should be documented in project README
- Template system should be prominently featured in getting started guide
- Consider integrating with popular TYPO3 project scaffolding tools
- Ensure template content follows current TYPO3 coding standards
