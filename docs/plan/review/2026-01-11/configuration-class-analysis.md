# Configuration Classes Usage Analysis

Date: 2026-01-11  
Purpose: Analyze configuration class usage patterns, dependencies, and overlapping responsibilities

## Executive Summary

The quality-tools package has multiple configuration classes with overlapping duties and complex dependencies. This analysis identifies patterns that may benefit from refactoring.

## Class Overview

### Core Configuration Classes

1. **Configuration** (`src/Configuration/Configuration.php`)
   - Primary data container with business logic methods
   - Path resolution and tool-specific configuration
   - Vendor directory detection integration

2. **EnhancedConfiguration** (`src/Configuration/EnhancedConfiguration.php`) 
   - Extends Configuration capabilities with metadata
   - Source tracking and conflict detection
   - Hierarchical configuration support

3. **YamlConfigurationLoader** (`src/Configuration/YamlConfigurationLoader.php`)
   - Simple YAML file loading
   - Basic validation and security
   - Single-file configuration support

4. **HierarchicalConfigurationLoader** (`src/Configuration/HierarchicalConfigurationLoader.php`)
   - Advanced multi-source configuration loading
   - Configuration discovery and merging
   - Returns EnhancedConfiguration instances

## Usage Patterns

### Command Classes

#### BaseCommand (src/Console/Command/BaseCommand.php)
- **Primary dependency**: YamlConfigurationLoader (line 449)
- **Method**: `getConfiguration()` returns standard Configuration
- **Usage**: All tool commands inherit from BaseCommand
- **Purpose**: Path resolution and project configuration

**Key Pattern**:
```php
protected function getConfiguration(InputInterface $input): Configuration
{
    $loader = $this->getYamlConfigurationLoader();
    $this->configuration = $loader->load($projectRoot);
}
```

#### ConfigShowCommand (src/Console/Command/ConfigShowCommand.php)
- **Primary dependency**: HierarchicalConfigurationLoader (line 52)
- **Method**: Uses hierarchical loading for enhanced features
- **Returns**: EnhancedConfiguration with source tracking
- **Purpose**: Display merged configuration with source attribution

**Key Pattern**:
```php
$loader = $this->getHierarchicalConfigurationLoader();
$enhancedConfiguration = $loader->load($projectRoot);
```

#### ConfigInitCommand (src/Console/Command/ConfigInitCommand.php)
- **Primary dependency**: YamlConfigurationLoader (line 79)
- **Method**: Uses simple loader for file existence checking
- **Purpose**: Create new configuration files

## Dependency Flow

```
BaseCommand
├── YamlConfigurationLoader
│   ├── ConfigurationValidator
│   ├── SecurityService
│   └── FilesystemService
└── Configuration (returned)
    ├── VendorDirectoryDetector
    ├── PathScanner
    └── Business logic methods

ConfigShowCommand
├── HierarchicalConfigurationLoader
│   ├── ConfigurationHierarchy
│   ├── ConfigurationDiscovery
│   ├── ConfigurationMerger
│   ├── ConfigurationValidator
│   ├── SecurityService
│   └── FilesystemService
└── EnhancedConfiguration (returned)
    ├── Source tracking
    ├── Conflict detection
    └── Configuration metadata
```

## Overlapping Duties Analysis

### 1. Configuration Data Access

**Configuration class**:
- `getProjectPhpVersion()`, `getProjectName()`, `getScanPaths()`
- Business logic methods for configuration access

**EnhancedConfiguration class**:
- Identical methods: `getProjectPhpVersion()`, `getProjectName()`, `getScanPaths()`
- **Overlap**: Duplicated implementation with same logic

### 2. Tool Configuration

**Configuration class**:
- `getToolConfig()`, `isToolEnabled()`, `getToolPaths()`
- Tool-specific configuration methods

**EnhancedConfiguration class**:
- Same methods plus `getToolConfigurationResolved()`
- **Overlap**: Core tool config logic duplicated

### 3. Path Resolution

**Configuration class**:
- `getResolvedPathsForTool()` - Full path resolution logic
- PathScanner integration

**EnhancedConfiguration class**:
- No path resolution methods
- **Gap**: Advanced configuration lacks path resolution capabilities

### 4. Configuration Loading

**YamlConfigurationLoader**:
- Simple single-file YAML loading
- Basic validation and security

**HierarchicalConfigurationLoader**:
- Multi-source configuration discovery
- Advanced merging and conflict detection
- **Overlap**: Both handle YAML parsing and validation

### 5. Validation

**Both loaders**:
- Use ConfigurationValidator
- Handle SecurityService integration
- **Overlap**: Identical validation logic in both loaders

## Architectural Issues

### Issue 1: Inconsistent Return Types

**Problem**: Commands use different loaders returning different types
- BaseCommand → Configuration
- ConfigShowCommand → EnhancedConfiguration

**Impact**: 
- Type inconsistency across command hierarchy
- Different capabilities available in different contexts

### Issue 2: Duplicated Business Logic

**Problem**: Configuration and EnhancedConfiguration duplicate methods
- 15+ identical method implementations
- Maintenance burden and potential divergence

**Examples**:
```php
// Configuration.php:46
public function getProjectPhpVersion(): string
{
    return $this->projectConfig['php_version'] ?? '8.3';
}

// EnhancedConfiguration.php:52
public function getProjectPhpVersion(): string
{
    $qualityTools = $this->data['quality-tools'] ?? [];
    $projectConfig = $qualityTools['project'] ?? [];
    return $projectConfig['php_version'] ?? '8.3';
}
```

### Issue 3: Missing Capabilities

**Problem**: EnhancedConfiguration lacks path resolution
- Configuration has `getResolvedPathsForTool()`
- EnhancedConfiguration has no path methods
- ConfigShowCommand can't display path information

### Issue 4: Complex Dependency Chains

**Problem**: HierarchicalConfigurationLoader creates many dependencies
- 5+ internal classes instantiated
- Complex object graph
- Difficult to test and maintain

## Program Flow Comparison

### Simple Configuration Flow (BaseCommand)
```
1. Create YamlConfigurationLoader
2. Load single configuration file
3. Return Configuration instance
4. Use business logic methods
```

### Advanced Configuration Flow (ConfigShowCommand)
```
1. Create HierarchicalConfigurationLoader
2. Create ConfigurationHierarchy
3. Create ConfigurationDiscovery  
4. Discover all configuration sources
5. Create ConfigurationMerger
6. Merge all configurations
7. Track sources and conflicts
8. Return EnhancedConfiguration
9. Display with source attribution
```

## Recommendations for Refactoring

### 1. Extract Common Interface
Create `ConfigurationInterface` with shared methods to ensure consistency.

### 2. Composition over Inheritance
Use composition to share business logic instead of duplicating implementations.

### 3. Unified Configuration Type
Consider making all commands use enhanced configuration for consistency.

### 4. Separate Concerns
- Configuration data: Pure data container
- Business logic: Separate service classes  
- Path resolution: Dedicated service
- Loading logic: Loader classes only

### 5. Factory Pattern
Use factory to abstract loader selection and return type unification.