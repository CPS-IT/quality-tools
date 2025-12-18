# Feature: Additional Packages/Paths Scanning

**Status:** Not Started  
**Estimated Time:** 4-6 hours  
**Layer:** MVP  
**Dependencies:** unified-configuration-system (Not Started)

## Description

Enable flexible configuration of additional packages and paths for quality tool scanning beyond the standard TYPO3 project structure. This allows projects to include custom paths, vendor-specific packages, or non-standard directory structures in quality analysis.

## Problem Statement

Current quality tool configurations are limited to predefined paths (packages/, config/system/, etc.). Projects with:

- Custom vendor namespaces (e.g., `fr/*`, `cpsit/*`)
- Non-standard directory structures
- Third-party extensions requiring analysis
- Monorepo setups with multiple package locations

Cannot easily configure quality tools to scan these additional paths.

## Goals

- Support flexible path configuration for all quality tools
- Enable vendor namespace-based path inclusion
- Maintain performance with large path sets
- Provide clear path resolution and validation

## Tasks

- [ ] Path Configuration System
  - [ ] Design path specification format (glob patterns, namespaces)
  - [ ] Implement path resolution and validation
  - [ ] Create path exclusion mechanisms
  - [ ] Add relative/absolute path normalization
- [ ] Tool Integration
  - [ ] Integrate additional paths with Rector configuration
  - [ ] Update Fractor to scan custom paths
  - [ ] Configure PHPStan for additional paths
  - [ ] Update PHP CS Fixer path handling
  - [ ] Integrate with TypoScript Lint path configuration
- [ ] Performance Optimization
  - [ ] Implement path caching and indexing
  - [ ] Add path filtering for large directories
  - [ ] Create incremental scanning capabilities
  - [ ] Optimize path matching algorithms

## Success Criteria

- [ ] Projects can specify custom paths using glob patterns
- [ ] Vendor namespace-based path inclusion works (e.g., `cpsit/*`, `fr/*`)
- [ ] Path resolution handles both relative and absolute paths
- [ ] Large directory scanning maintains acceptable performance
- [ ] Path validation prevents invalid configurations

## Technical Requirements

### Path Specification Format

Support multiple path specification formats:
- Glob patterns: `packages/*/Classes/**/*.php`
- Vendor namespaces: `cpsit/*`, `fr/*` (resolves to vendor directories)
- Direct paths: `src/`, `app/Classes/`
- Exclusion patterns: `!packages/legacy/*`

### Path Resolution Rules

1. Resolve vendor namespace patterns to actual paths
2. Convert relative paths to absolute based on project root
3. Validate path existence and accessibility
4. Apply exclusion patterns after inclusion
5. Deduplicate and normalize final path list

## Implementation Plan

### Phase 1: Path Configuration

1. Define path specification schema
2. Implement path pattern parsing and validation
3. Create path resolution algorithms
4. Add configuration validation

### Phase 2: Tool Integration

1. Update each quality tool configuration generation
2. Implement path filtering for tool-specific requirements
3. Add path debugging and introspection
4. Test with various project structures

## Configuration Schema

```yaml
quality-tools:
  paths:
    # Standard paths (existing)
    scan:
      - "packages/"
      - "config/system/"
    
    # Additional custom paths
    additional:
      - "src/**/*.php"                    # Custom source directory
      - "app/Classes/**/*.php"            # Alternative class directory
      - "vendor/cpsit/*/Classes/**/*.php" # Vendor namespace pattern
      - "vendor/fr/*/Classes/**/*.php"    # Another vendor pattern
      - "custom-extensions/*/Classes/"    # Custom extension location
    
    # Exclusion patterns
    exclude:
      - "packages/legacy/*"               # Exclude legacy packages
      - "vendor/*/Tests/"                 # Exclude vendor tests
      - "*.min.js"                       # Exclude minified files
    
    # Tool-specific overrides
    tools:
      rector:
        additional:
          - "config/custom/*.php"         # Tool-specific additional paths
      fractor:
        additional:
          - "config/sites/*/setup.typoscript"
```

## Performance Considerations

- Path pattern compilation and caching
- Efficient directory traversal algorithms
- Lazy evaluation of large path sets
- File system call optimization
- Memory-efficient path storage

## Testing Strategy

- Unit tests for path pattern parsing and resolution
- Integration tests with various project structures
- Performance tests with large directory structures
- Validation tests for edge cases and invalid patterns
- End-to-end tests with all quality tools

## Backward Compatibility

- Default path configuration remains unchanged
- Existing projects continue working without modification
- Additional paths are additive, not replacements
- Clear migration path for projects wanting advanced path configuration

## Risk Assessment

**Low:**
- Additive feature doesn't break existing functionality
- Path validation prevents most configuration errors
- Performance impact limited to projects using additional paths

**Mitigation:**
- Comprehensive path validation and error messages
- Performance monitoring and optimization
- Fallback to standard paths if additional paths fail
- Clear documentation for path pattern syntax

## Future Enhancements

- Interactive path configuration tool
- Path auto-discovery based on project analysis
- Integration with IDE for path completion
- Path usage analytics and optimization suggestions
- Dynamic path configuration based on project changes

## Notes

- Focus on common use cases first (vendor namespaces, custom directories)
- Ensure path patterns are intuitive and well-documented
- Consider security implications of arbitrary path scanning
- Plan for cross-platform path handling differences
