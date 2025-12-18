# Feature: Vendor Folder Derivation

**Status:** Not Started  
**Estimated Time:** 4-6 hours  
**Layer:** MVP  
**Dependencies:** unified-configuration-system (Not Started)

## Description

Automatically derive the vendor folder path from composer.json or using Composer's InstalledPackages class, eliminating the need for hardcoded vendor paths and supporting non-standard project structures.

## Problem Statement

Current implementation assumes standard vendor folder locations, causing issues with:

- Projects with custom vendor directory configurations
- Symlinked or relocated vendor directories
- Monorepo setups with shared vendor directories
- Projects using Composer's `vendor-dir` configuration option
- Development environments with non-standard layouts

## Goals

- Automatically detect vendor directory location from Composer configuration
- Support custom vendor directory configurations
- Handle symlinked and relocated vendor directories
- Maintain performance with efficient detection algorithms
- Provide fallback mechanisms for edge cases

## Tasks

- [ ] Vendor Detection Implementation
  - [ ] Implement composer.json parsing for vendor-dir configuration
  - [ ] Create Composer InstalledPackages class integration
  - [ ] Add vendor directory validation and existence checking
  - [ ] Implement fallback detection mechanisms
- [ ] Path Resolution
  - [ ] Create relative to absolute path conversion
  - [ ] Handle symlinked vendor directories
  - [ ] Add cross-platform path normalization
  - [ ] Implement path caching for performance
- [ ] Configuration Integration
  - [ ] Update all tool configurations to use detected vendor path
  - [ ] Modify path resolution in configuration templates
  - [ ] Add vendor path debugging and introspection
  - [ ] Create configuration validation with detected paths
- [ ] Error Handling and Fallbacks
  - [ ] Add graceful handling of detection failures
  - [ ] Implement manual vendor path override option
  - [ ] Create clear error messages for path issues
  - [ ] Add vendor directory health checks

## Success Criteria

- [ ] Vendor directory is automatically detected from Composer configuration
- [ ] Custom vendor-dir configurations are properly handled
- [ ] Symlinked and relocated vendor directories work correctly
- [ ] All tools use the correct vendor path automatically
- [ ] Clear error messages for unresolvable vendor paths

## Technical Requirements

### Detection Methods (in order of preference)

1. **Composer InstalledPackages Class** - Most reliable, uses Composer's own logic
2. **composer.json parsing** - Read vendor-dir configuration directly
3. **Environment detection** - Check standard locations and environment variables
4. **Fallback paths** - Try common vendor directory locations

### Supported Scenarios

- Standard `vendor/` directory in project root
- Custom vendor directory via `composer.json` `config.vendor-dir`
- Symlinked vendor directories
- Shared vendor directories in monorepo setups
- Vendor directories outside project root

## Implementation Plan

### Phase 1: Basic Detection

1. Implement composer.json parsing for vendor-dir
2. Add Composer InstalledPackages class integration
3. Create basic path resolution and validation
4. Add fallback detection mechanisms

### Phase 2: Integration and Optimization

1. Update all tool configurations to use detected paths
2. Add path caching and performance optimization
3. Implement comprehensive error handling
4. Create debugging and introspection tools

## Configuration Schema

```yaml
vendor_detection:
  # Detection preferences
  methods:
    - "composer_installed_packages"  # Preferred method
    - "composer_json_parsing"
    - "environment_detection"
    - "standard_locations"
  
  # Caching options
  cache:
    enabled: true
    ttl: 300  # 5 minutes
  
  # Override options
  override:
    path: null  # Manual override if needed
    validate: true
  
  # Fallback options
  fallbacks:
    - "vendor"
    - "../vendor"
    - "../../vendor"
```

## Technical Implementation

```php
<?php

class VendorDirectoryDetector
{
    public function detectVendorPath(string $projectRoot): string
    {
        // Method 1: Use Composer's InstalledPackages
        if (class_exists('Composer\\InstalledVersions')) {
            $vendorDir = \Composer\InstalledVersions::getRootPackage()['install_path'] . '/vendor';
            if (is_dir($vendorDir)) {
                return $vendorDir;
            }
        }
        
        // Method 2: Parse composer.json
        $composerFile = $projectRoot . '/composer.json';
        if (file_exists($composerFile)) {
            $config = json_decode(file_get_contents($composerFile), true);
            if (isset($config['config']['vendor-dir'])) {
                $vendorDir = $projectRoot . '/' . $config['config']['vendor-dir'];
                if (is_dir($vendorDir)) {
                    return realpath($vendorDir);
                }
            }
        }
        
        // Method 3: Standard fallbacks
        $fallbacks = ['vendor', '../vendor', '../../vendor'];
        foreach ($fallbacks as $fallback) {
            $vendorDir = $projectRoot . '/' . $fallback;
            if (is_dir($vendorDir)) {
                return realpath($vendorDir);
            }
        }
        
        throw new VendorDirectoryNotFoundException('Could not detect vendor directory');
    }
}
```

## Performance Considerations

- Caching of vendor path detection results
- Efficient composer.json parsing
- Minimal file system operations
- Lazy evaluation where possible

## Testing Strategy

- Unit tests for various vendor directory configurations
- Integration tests with different project structures
- Performance tests for detection algorithms
- Edge case testing for symlinks and unusual setups
- Cross-platform compatibility testing

## Backward Compatibility

- Existing hardcoded vendor paths continue working as fallbacks
- Manual vendor path override available for edge cases
- No breaking changes to existing configurations
- Gradual migration to automatic detection

## Risk Assessment

**Low:**
- Detection algorithms are read-only operations
- Multiple fallback mechanisms prevent total failure
- Manual override available for problematic cases

**Mitigation:**
- Comprehensive testing with various project structures
- Clear error messages and debugging information
- Manual override option for unsupported scenarios
- Fallback to standard locations if detection fails

## Future Enhancements

- Support for multiple vendor directories in monorepos
- Integration with Composer plugins and extensions
- Performance optimization for large projects
- Support for alternative package managers
- Advanced vendor directory health checking

## Notes

- Prioritize reliability and compatibility over performance
- Ensure detection works in various development environments
- Consider CI/CD and deployment scenario differences
- Plan for future Composer changes and updates
- Focus on most common vendor directory scenarios first
