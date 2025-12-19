# Feature 017: Single Package Scanning

**Status:** Not Started  
**Estimated Time:** 8-12 hours  
**Layer:** MVP  
**Dependencies:** 010-unified-yaml-configuration-system (Not Started)

## Description

Enable quality tools to scan and analyze single packages in place instead of requiring full project scanning. This allows the tools to be used effectively in CI/CD pipelines for individual packages and supports focused development workflows.

## Problem Statement

Currently, quality tools are designed to scan entire TYPO3 projects, which creates issues for:

- CI/CD pipelines that want to analyze only changed packages
- Development workflows focused on specific extensions
- Monorepo setups where packages are developed independently
- Package-specific quality gates and validation
- Vendor packages that need isolated analysis

## Goals

- Enable scanning of individual packages in their vendor location
- Support CI/CD integration for package-specific quality checks
- Maintain compatibility with full project scanning
- Provide package-specific configuration and reporting

## Tasks

- [ ] Package Detection and Isolation
  - [ ] Implement package root detection algorithm
  - [ ] Create package dependency resolution
  - [ ] Design package-specific configuration loading
  - [ ] Add package boundary validation
- [ ] Tool Adaptation for Single Packages
  - [ ] Adapt Rector for single package scanning
  - [ ] Update Fractor for package-specific TypoScript
  - [ ] Configure PHPStan for isolated package analysis
  - [ ] Modify PHP CS Fixer for package scope
  - [ ] Update TypoScript Lint for package configurations
- [ ] CI/CD Integration
  - [ ] Create package change detection
  - [ ] Implement incremental scanning capabilities
  - [ ] Add package-specific reporting
  - [ ] Create CI/CD pipeline examples and documentation
- [ ] Configuration Management
  - [ ] Support package-level configuration files
  - [ ] Implement configuration inheritance from project level
  - [ ] Add package-specific rule exclusions
  - [ ] Create configuration validation for package scope

## Success Criteria

- [ ] Tools can analyze packages from their vendor location (e.g., `vendor/cpsit/zug-sitepackage`)
- [ ] Package-specific configuration files are respected
- [ ] CI/CD pipelines can validate individual packages efficiently
- [ ] Package scanning maintains same quality standards as full project
- [ ] Reports clearly indicate package scope and boundaries

## Technical Requirements

### Package Detection

- Identify package root from composer.json location
- Resolve package dependencies and boundaries
- Support both installed packages and development packages
- Handle symlinked packages in development environments

### Configuration Scope

- Package-level configuration inherits from project defaults
- Package-specific overrides apply only within package boundary
- Tool configurations adapt to package structure
- Dependency analysis respects package boundaries

### Integration Points

- Git hook integration for changed packages
- CI/CD environment variable support
- Package change detection algorithms
- Incremental analysis and caching

## Implementation Plan

### Phase 1: Package Detection and Boundaries

1. Implement package root detection
2. Create package boundary definition
3. Add dependency resolution within package scope
4. Design configuration inheritance model

### Phase 2: Tool Integration

1. Adapt each quality tool for package-specific scanning
2. Implement package-aware configuration loading
3. Create package-specific reporting formats
4. Test with various package structures

### Phase 3: CI/CD Integration

1. Create change detection algorithms
2. Implement incremental scanning
3. Add CI/CD pipeline templates
4. Document integration patterns

## Configuration Schema

Extends unified YAML configuration from Feature 010:

```yaml
# Example: Package-specific configuration
quality-tools:
  # Package mode configuration
  package:
    mode: "package"  # Enable package-specific scanning
    root: "vendor/cpsit/zug-sitepackage"  # Package root directory
    
    # Package-specific path overrides
    paths:
      scan:
        - "Classes/"
        - "Configuration/"
        - "Resources/"
      exclude:
        - "Tests/"
        - "Documentation/"
    
    # Package-specific tool configuration
    tools:
      rector:
        # Only apply specific rules in package context
        level: "typo3-13"
        skip:
          - "Some\\Specific\\Rector\\Rule"
      phpstan:
        # Package-specific PHPStan level
        level: 6
        paths:
          - "Classes/"
```

## File Structure

```
vendor/cpsit/zug-sitepackage/     # Package being analyzed
├── composer.json                 # Package definition
├── quality-tools.yaml           # Package-specific config
├── Classes/                     # Scanned directory
├── Configuration/               # Scanned directory
└── Tests/                       # Potentially excluded
```

## Backward Compatibility

- Full project scanning remains the default behavior
- Package mode is opt-in via command-line flag or configuration
- Existing project configurations continue working
- Package mode supplements rather than replaces project mode

## Performance Considerations

- Reduced scanning scope improves performance
- Package-specific caching strategies
- Dependency analysis optimization
- Incremental analysis for changed packages only

## Security Considerations

- Package boundary enforcement prevents unauthorized file access
- Configuration isolation between packages
- Validation of package-specific configuration sources
- Secure handling of package dependencies

## Testing Strategy

- Unit tests for package detection and boundary logic
- Integration tests with various package structures
- CI/CD pipeline simulation and testing
- Performance tests comparing package vs project scanning
- Security tests for boundary enforcement

## Dependencies

- **Feature 010 (Unified YAML Configuration System)**: Provides configuration foundation and inheritance for package-specific settings
- composer/composer: For package information and dependency resolution
- symfony/finder: For efficient file scanning within package boundaries

## Risk Assessment

**Medium:**
- Complex package boundary detection logic
- Dependency resolution challenges in isolated packages
- CI/CD integration complexity across different platforms

**Mitigation:**
- Comprehensive testing with various package scenarios
- Clear documentation for CI/CD integration patterns
- Fallback to project-wide scanning if package detection fails
- Extensive validation of package boundaries

## Future Enhancements

- Multi-package analysis for related packages
- Package quality scoring and benchmarking
- Integration with package registries and catalogs
- Automated package quality reporting
- Package-specific quality gates and policies

## Notes

- Focus on common CI/CD use cases first
- Ensure package mode doesn't compromise quality analysis depth
- Consider monorepo and multi-package project scenarios
- Document clear guidelines for when to use package vs project mode
- Plan for integration with popular CI/CD platforms (GitHub Actions, GitLab CI, etc.)
- Maintain consistency with Feature 010 YAML configuration structure