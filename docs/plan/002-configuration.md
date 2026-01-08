# Configuration System Implementation: CPSIT Quality Tools CLI

**Implementation Status:** Work in Progress
**Estimated Timeline:** 24–34 hours total
**Dependencies:** MVP (001) must be completed

## Overview

This iteration focuses on implementing a comprehensive configuration system that enables flexible project setup, path customization, and hierarchical configuration management. The configuration system provides the foundation for advanced project detection and tool customization beyond the basic MVP functionality.

## Current State Analysis

### Existing Infrastructure
- **Basic Project Detection**: Simple composer.json traversal for project root detection
- **Hard-coded Paths**: Fixed configuration paths in `config/` directory
- **Limited Flexibility**: No support for custom package paths or vendor locations
- **Single Configuration**: No override or environment-specific configuration support

### Target Improvements
- **Unified Configuration**: Single YAML-based configuration system
- **Flexible Path Detection**: Support for custom package and vendor locations
- **Hierarchical Overrides**: Project-specific and environment-specific configurations
- **Developer Experience**: Human-readable YAML with comments and documentation

## Goals

### Primary Objectives
1. **Unified Configuration Management**: Replace hard-coded paths with flexible YAML configuration
2. **Enhanced Path Detection**: Support non-standard project structures and custom paths
3. **Configuration Hierarchy**: Enable project-specific overrides and customizations
4. **Developer-Friendly Format**: YAML with comments and clear documentation

### Success Criteria
- All tools work with custom package and vendor paths
- Project-specific configurations override default settings
- YAML configuration is intuitive and well-documented
- Backward compatibility with existing project structures maintained

## Features in This Iteration

### Feature 010: Unified YAML Configuration System (6–8 hours)
**Goal**: Replace hard-coded configurations with flexible YAML-based system
**Dependencies**: None (builds on MVP)
**Deliverables**:
- Default `quality-tools.yaml` configuration file
- YAML configuration loader and validator
- Integration with existing command structure

### Feature 014: Vendor Folder Derivation (4-6 hours)
**Goal**: Automatic detection of vendor folder in non-standard project structures
**Dependencies**: Feature 010 (YAML configuration)
**Deliverables**:
- Automatic vendor path detection algorithm
- Support for custom vendor locations
- Fallback mechanisms for edge cases

### Feature 013: Additional Packages Paths Scanning (4–6 hours)
**Goal**: Support custom package locations beyond standard `packages/` directory
**Dependencies**: Features 010 (YAML configuration), 014 (vendor folder detection)
**Deliverables**:
- Configurable package path scanning
- Support for multiple package directories relative to project and vendor locations
- Path validation and error handling

### Feature 015: Configuration Overwrites (6–8 hours)
**Goal**: Hierarchical configuration system with project and environment overrides
**Dependencies**: Features 010, 013, 014 (complete configuration system)
**Deliverables**:
- Configuration hierarchy resolution
- Project-specific override support
- Environment variable integration

### Feature 016: Fail on Warnings Configuration (4–6 hours)
**Goal**: Configurable exit code behavior for linting tools to ensure CI/CD reliability
**Dependencies**: Feature 010 (YAML configuration)
**Deliverables**:
- `failOnWarnings` configuration option for all tools
- Global and tool-specific warning detection settings
- CI/CD-compatible exit code management

## Critical Issues and Refactoring Decision

### Code Quality Review Impact (2026-01-07)

Based on comprehensive architectural and implementation analysis, **iteration 002 will address critical code quality issues before implementing Feature 015 (Configuration Overwrites)**. This decision ensures architectural stability and reduces implementation risk for the configuration hierarchy system.

### Critical Issues Requiring Immediate Attention

**Issue 007: PathScanner Exclusion Logic Refactoring (3–4 hours)**
- **Problem**: High cyclomatic complexity in `PathScanner::applyExclusions()` with nested conditions
- **Impact**: Affects Feature 015 implementation as configuration resolution depends on PathScanner logic
- **Priority**: Critical - Feature 015 directly depends on reliable path resolution

**Issue 008: Resource Cleanup Temporary Files (2–3 hours)**
- **Problem**: PHPStan command creates temporary files without cleanup, test isolation issues
- **Impact**: Configuration hierarchy testing requires clean test environment
- **Priority**: Critical - Affects testing reliability for Feature 015 validation

**Issue 009: Security Hardening Environment Variables (2–3 hours)**
- **Problem**: Configuration supports environment variable substitution without proper sanitization
- **Impact**: Feature 015 extends environment variable integration in configuration hierarchy
- **Priority**: Critical - Security foundation required before configuration expansion

**Issue 018: BaseCommand executeProcess Method Refactoring (2–3 hours)**
- **Problem**: 54-line method with multiple responsibilities in core command execution
- **Impact**: Feature 015 adds configuration resolution complexity to already complex method
- **Priority**: High - Simplifies Feature 015 integration and improves maintainability

### Revised Implementation Timeline

**Phase 3: Critical Refactoring (8–12 hours)**
1. PathScanner exclusion logic refactoring (3–4 hours)
2. Resource cleanup implementation (2-3 hours)
3. Security hardening for environment variables (2–3 hours)
4. BaseCommand executeProcess method refactoring (2–3 hours)

**Total Revised Timeline: 32–46 hours** (8–12 hours additional for refactoring)

### Benefits of Refactoring-First Approach

**Reduced Implementation Risk**: Feature 015 complexity reduced from high to medium with clean foundation
**Improved Testing Reliability**: Configuration hierarchy validation on stable test infrastructure
**Security Foundation**: Environment variable handling secured before Feature 015 integration
**Maintainable Architecture**: Clean separation of concerns simplifies future enhancements

## Implementation Strategy

### Phase 1: Foundation (Feature 010)
**Objective**: Establish YAML configuration infrastructure

#### Tasks
1. **YAML Configuration Schema**
   - Define comprehensive configuration structure
   - Create default `config/quality-tools.yaml`
   - Document all configuration options with comments

2. **Configuration Loader**
   - `src/Configuration/ConfigurationLoader.php`
   - YAML parsing and validation
   - Error handling for malformed configurations

3. **Integration with Commands**
   - Update `BaseCommand` to use YAML configuration
   - Maintain backward compatibility with existing paths
   - Configuration override support via command options

### Phase 2: Path Flexibility (Features 014, 013)
**Objective**: Enable flexible project structure support

#### Tasks
1. **Vendor Path Detection** (Feature 014)
   - Automatic vendor folder location algorithm using Composer
   - Support for Composer-generated vendor paths
   - Custom vendor location configuration
   - Fallback to standard `vendor/` location

2. **Package Path Scanning** (Feature 013)
   - Configurable package directory discovery beyond vendor
   - Support for multiple package locations (packages/, extensions/, etc.)
   - Path validation relative to project root and vendor location
   - Integration with vendor folder detection

### Phase 3: Critical Issues Resolution
**Objective**: Establish stable architectural foundation for configuration hierarchy

#### Tasks
1. **PathScanner Refactoring**
    - Extract exclusion logic into `PathExclusionFilter` strategy class
    - Implement early returns to flatten nested conditions
    - Add comprehensive unit tests for edge cases

2. **Resource Cleanup Implementation**
    - Add temporary file cleanup in PHPStan command using disposable pattern
    - Fix test cleanup issues with proper test isolation
    - Implement resource management for all temporary assets

3. **Security Hardening**
    - Sanitize environment variable inputs in configuration parsing
    - Use secure temporary file creation with proper permissions
    - Implement input validation for configuration values

4. **BaseCommand Refactoring**
    - Extract command preparation, execution, and output handling into separate methods
    - Reduce method complexity to under 35 lines with single responsibility
    - Improve error handling with structured responses

### Phase 4: Configuration Hierarchy (Feature 015)
**Objective**: Complete hierarchical configuration system

#### Tasks
1. **Override System**
   - Project-specific configuration loading
   - Environment variable integration
   - Configuration merge and priority handling

2. **Validation and Documentation**
   - Comprehensive configuration validation
   - Error messages and troubleshooting guides
   - Example configurations for common scenarios

## Dependencies Between Features

```
010 (YAML Config)
├── 014 (Vendor Detection) -> depends on 010
├── 013 (Package Paths) -> depends on 010, 014
└── 015 (Overwrites) -> depends on 010, 014, 013
```

**Rationale**: Vendor folder detection must come before additional package path scanning because package paths are defined relative to the established vendor location in a Composer-managed project.

## Technical Requirements

### New Dependencies
- `symfony/yaml: ^6.0|^7.0` - YAML parsing and generation
- `symfony/filesystem: ^6.0|^7.0` - File system operations

### Configuration Schema
```yaml
# quality-tools.yaml
project:
  root: "." # Project root directory
  vendor: "vendor" # Vendor directory (auto-detected if not specified)
  packages:
    - "packages" # Default package directories
    - "extensions"

tools:
  rector:
    config: "config/rector.php"
    paths: ["packages", "config"]

  phpstan:
    config: "config/phpstan.neon"
    level: 6
```

## Risk Assessment

### Technical Risks

#### Risk: YAML Parsing Performance
**Impact**: Low - Configuration loaded once per command execution
**Probability**: Low - Symfony YAML component is optimized
**Mitigation**: Cache parsed configuration, lazy loading

#### Risk: Configuration Complexity
**Impact**: Medium - Over-configuration could confuse users
**Probability**: Medium - Tendency to add too many options
**Mitigation**: Sensible defaults, comprehensive documentation, validation

#### Risk: Backward Compatibility
**Impact**: High - Breaking existing project setups
**Probability**: Low - Designed with compatibility in mind
**Mitigation**: Fallback mechanisms, extensive testing, migration guides

### Implementation Risks

#### Risk: Feature Interdependencies
**Impact**: Medium - Complex dependencies could cause integration issues
**Probability**: Low - Clear dependency hierarchy established
**Mitigation**: Phased implementation, integration testing between features

## Success Metrics

### Quantitative Metrics
- Support 100% of existing project structures
- Configuration loading time under 50ms
- Zero breaking changes for existing users
- All features working with custom paths

### Qualitative Metrics
- Intuitive YAML configuration format
- Clear documentation and examples
- Smooth migration path for existing projects
- Enhanced flexibility for complex project structures

## Future Integration

This configuration system provides the foundation for:
- Advanced reporting configuration (iteration 003)
- Tool-specific customization options
- Environment-specific quality standards
- Team-shared configuration profiles

The configuration system establishes a solid foundation for all future enhancements while maintaining simplicity for basic use cases.
