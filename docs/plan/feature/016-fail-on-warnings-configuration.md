# Feature 016: Fail on Warnings Configuration

**Status:** Not Started
**Estimated Time:** 4-6 hours
**Layer:** 002 Configuration
**Dependencies:** 010-unified-yaml-configuration-system (Completed)

## Description

Enable CI/CD-friendly exit code behavior by default for all linting tools, with configurable options to disable failure on warnings when needed. By default, all tools will fail (exit code 1) when warnings are detected, ensuring reliable CI/CD pipeline behavior.

## Problem Statement

Currently, some quality tools return exit code 0 even when they detect warnings or issues, causing CI/CD pipelines to pass when they should fail. This creates false positive build results and prevents proper quality gate enforcement:

- TypoScript Lint may return 0 for warnings
- EditorConfig CLI returns 0 for formatting issues
- Other linting tools may have inconsistent exit code behavior
- CI/CD systems rely on non-zero exit codes to detect failures

## Goals

- **Default CI/CD-friendly behavior**: All tools fail on warnings by default (no configuration required)
- **Optional warning tolerance**: Provide global and tool-specific options to disable failure on warnings
- **Zero configuration required**: Works out-of-the-box for CI/CD reliability
- **Flexible overrides**: Allow projects to customize behavior when warnings should not fail builds
- **Consistent behavior**: Ensure all tools follow the same exit code patterns

## Tasks

- [ ] **Configuration Schema Extension**
  - [ ] Add `tolerateWarnings` option to output configuration schema (default: false)
  - [ ] Add tool-specific `tolerateWarnings` overrides to each tool schema
  - [ ] Update `ConfigurationValidator.php` with new schema definitions
  - [ ] Ensure default behavior fails on warnings (no configuration required)
- [ ] **Base Command Enhancement**
  - [ ] Modify `BaseCommand::executeProcess()` to fail on warnings by default
  - [ ] Add `shouldTolerateWarnings()` method to check configuration overrides
  - [ ] Implement `hasWarnings()` method with tool-specific detection logic
  - [ ] Add warning pattern detection for each tool type
- [ ] **Tool-Specific Warning Detection**
  - [ ] Implement TypoScript Lint warning detection patterns
  - [ ] Add pattern detection for future EditorConfig integration
  - [ ] Create extensible pattern matching system for other tools
  - [ ] Add output parsing logic for common warning formats
- [ ] **Configuration Integration**
  - [ ] Add configuration accessors in `Configuration.php`
  - [ ] Update existing commands to use new warning detection
  - [ ] Ensure tool-specific overrides work correctly
  - [ ] Add configuration validation and error handling
- [ ] **Testing and Documentation**
  - [ ] Add unit tests for warning detection logic
  - [ ] Add integration tests for exit code behavior
  - [ ] Update YAML configuration documentation
  - [ ] Add examples for different tool configurations

## Success Criteria

- [ ] All linting commands fail on warnings by default (zero configuration required)
- [ ] CI/CD pipelines fail appropriately when warnings are detected
- [ ] `tolerateWarnings` overrides work correctly for projects that need warning tolerance
- [ ] Warning detection works for TypoScript Lint and is extensible for other tools
- [ ] No breaking changes for existing projects (improved CI/CD reliability)
- [ ] Configuration validation prevents invalid settings
- [ ] Comprehensive test coverage for all new functionality

## Configuration Schema

```yaml
# Default behavior: All tools fail on warnings (zero configuration required)
# Optional configuration to disable warning failures when needed:

quality-tools:
  output:
    tolerateWarnings: false  # Global override (default: false = fail on warnings)

  tools:
    typoscript-lint:
      enabled: true
      # tolerateWarnings: false  # Tool-specific override (optional)

    rector:
      enabled: true
      tolerateWarnings: true  # Allow warnings without failing (special case)

    # Future tool integrations
    editorconfig:
      enabled: true
      # tolerateWarnings: false  # Default behavior (optional to specify)
```

## Technical Requirements

### Configuration System
- Extend existing YAML schema with `tolerateWarnings` boolean option (default: false)
- Support global setting in `output` section to override default behavior
- Support tool-specific overrides in individual tool configurations
- Default behavior: fail on warnings (no configuration required for CI/CD compatibility)
- Validate configuration values during loading

### Warning Detection System
- Parse tool output for warning indicators
- Support different warning formats across tools
- Extensible pattern matching for future tool integrations
- Handle edge cases like mixed success/warning outputs
- Performance-optimized output scanning

### Exit Code Management
- Preserve original tool exit codes when warnings not detected
- Return exit code 1 when warnings found and `failOnWarnings: true`
- Maintain existing error handling for tool failures
- Support tools that already return proper exit codes

## Implementation Plan

### Phase 1: Core Configuration (2-3 hours)
1. Extend YAML schema with `failOnWarnings` options
2. Update `ConfigurationValidator.php` with new validation rules
3. Add configuration accessor methods to `Configuration.php`
4. Create basic unit tests for configuration loading

### Phase 2: Warning Detection System (2-3 hours)
1. Modify `BaseCommand::executeProcess()` to support warning detection
2. Implement warning detection patterns for TypoScript Lint
3. Add extensible pattern matching system for future tools
4. Create comprehensive integration tests

## File Structure

```
src/
└── Console/Command/
    └── BaseCommand.php          # Enhanced with warning detection
└── Configuration/
    ├── Configuration.php        # New configuration accessors
    └── ConfigurationValidator.php  # Extended schema validation
└── Utility/
    └── WarningDetector.php      # New utility for pattern matching
tests/
├── Unit/
│   ├── Configuration/
│   │   └── ConfigurationTest.php   # Extended test coverage
│   └── Utility/
│       └── WarningDetectorTest.php  # New test suite
└── Integration/
    └── Console/Command/
        └── WarningDetectionTest.php # Integration tests
```

## Backward Compatibility

- Default `failOnWarnings: true` ensures existing behavior is preserved for CI/CD
- Tools that already return proper exit codes remain unaffected
- Existing YAML configurations continue to work without modification
- No breaking changes to command line interfaces
- Graceful fallback when warning detection patterns are not available

## Performance Considerations

- Warning detection only activates when `failOnWarnings: true`
- Efficient pattern matching using regex compilation
- Minimal overhead for output parsing (< 10ms for typical tool outputs)
- Cached pattern compilation for repeated command executions
- Memory-efficient output scanning for large tool outputs

## Testing Strategy

- **Unit Tests**: Configuration loading, warning detection patterns, exit code logic
- **Integration Tests**: End-to-end command execution with warning scenarios
- **Tool-Specific Tests**: Verify warning detection for each supported tool
- **Performance Tests**: Ensure minimal overhead for warning detection
- **CI/CD Tests**: Validate proper exit codes in automated pipeline scenarios

## Risk Assessment

**Low Risk:**
- Configuration changes are additive and backward compatible
- Warning detection is isolated to specific code paths
- Fallback behavior preserves existing functionality

**Mitigation:**
- Comprehensive test suite covers edge cases and tool variations
- Progressive rollout allows validation before full deployment
- Configuration validation prevents invalid settings

## Future Enhancements

- EditorConfig CLI integration with warning detection
- Custom warning pattern configuration for project-specific tools
- Warning severity levels (error vs warning vs info)
- Integration with reporting systems for warning analytics
- Advanced filtering options for specific warning types

## Notes

- This feature is critical for CI/CD reliability and should be prioritized
- Warning detection patterns may need adjustment based on tool version differences
- Consider tool-specific exit code behaviors when implementing detection logic
- Ensure consistent behavior across all supported tools and platforms
