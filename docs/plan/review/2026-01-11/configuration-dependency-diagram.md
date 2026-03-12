# Configuration Classes Dependency Diagram

Date: 2026-01-11
Purpose: Visual representation of configuration class dependencies and program flow

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           COMMAND LAYER                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  BaseCommand               ConfigShowCommand           ConfigInitCommand    │
│  ├─ YamlConfigurationLoader ├─ HierarchicalConfigurationLoader ├─ YamlConfigurationLoader │
│  └─ → Configuration         └─ → EnhancedConfiguration  └─ → (file creation) │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CONFIGURATION LAYER                                │
├─────────────────────────────────────────────────────────────────────────────┤
│           Configuration                    EnhancedConfiguration            │
│           ├─ Project config               ├─ All Configuration methods      │
│           ├─ Tool config                  ├─ Source tracking                │
│           ├─ Path resolution              ├─ Conflict detection             │
│           └─ Business logic               └─ Metadata & debug info          │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          LOADER LAYER                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  YamlConfigurationLoader            HierarchicalConfigurationLoader        │
│  ├─ Single file loading             ├─ Multi-source discovery              │
│  ├─ Basic validation                ├─ Configuration merging               │
│  └─ Simple security                 ├─ Conflict tracking                   │
│                                     └─ Advanced validation                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SUPPORT LAYER                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  ConfigurationHierarchy    ConfigurationDiscovery    ConfigurationMerger   │
│  ├─ File paths             ├─ File scanning          ├─ Data merging        │
│  ├─ Precedence rules       ├─ Source identification  ├─ Conflict detection  │
│  └─ Directory structure    └─ Error collection       └─ Source mapping      │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SERVICE LAYER                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  ConfigurationValidator    SecurityService    FilesystemService            │
│  ├─ JSON Schema validation ├─ Environment vars ├─ File operations          │
│  ├─ Error reporting        ├─ Input sanitization ├─ Path validation        │
│  └─ Schema compliance      └─ Security checks   └─ Safe file handling      │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          UTILITY LAYER                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  VendorDirectoryDetector   PathScanner       ProjectAnalyzer               │
│  ├─ Vendor path detection  ├─ Path resolution ├─ Project metrics           │
│  ├─ Fallback strategies    ├─ Pattern matching ├─ File analysis            │
│  └─ Debug information      └─ Path validation  └─ Optimization hints       │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Detailed Class Dependencies

### YamlConfigurationLoader Dependencies

```
YamlConfigurationLoader
├── ConfigurationValidator (constructor injection)
│   ├── JSON Schema validation
│   └── Error collection
├── SecurityService (constructor injection)
│   ├── Environment variable interpolation
│   ├── Input sanitization
│   └── Path security validation
├── FilesystemService (constructor injection)
│   ├── File existence checking
│   ├── File reading operations
│   └── Path normalization
└── → Returns: Configuration
    ├── VendorDirectoryDetector (lazy instantiation)
    ├── PathScanner (lazy instantiation)
    └── Business logic methods
```

### HierarchicalConfigurationLoader Dependencies

```
HierarchicalConfigurationLoader
├── ConfigurationValidator (constructor injection)
├── SecurityService (constructor injection)
├── FilesystemService (constructor injection)
├── ConfigurationHierarchy (runtime creation)
│   ├── Project root analysis
│   ├── Configuration file discovery
│   └── Precedence rule application
├── ConfigurationDiscovery (runtime creation)
│   ├── File system scanning
│   ├── Configuration parsing
│   ├── Error collection
│   └── Source identification
├── ConfigurationMerger (runtime creation)
│   ├── Multi-source merging
│   ├── Conflict detection
│   ├── Source mapping
│   └── Data validation
└── → Returns: EnhancedConfiguration
    ├── All Configuration methods (duplicated)
    ├── Source tracking capabilities
    ├── Conflict reporting
    └── Debug information
```

## Program Flow Diagrams

### Simple Configuration Loading (BaseCommand)

```
[BaseCommand::getConfiguration()]
                │
                ▼
    [Create YamlConfigurationLoader]
                │
                ▼
       [Load single YAML file]
                │
                ▼
     [Validate with ConfigurationValidator]
                │
                ▼
    [Interpolate with SecurityService]
                │
                ▼
       [Read with FilesystemService]
                │
                ▼
      [Create Configuration instance]
                │
                ▼
        [Set project root]
                │
                ▼
     [Initialize VendorDirectoryDetector]
                │
                ▼
        [Initialize PathScanner]
                │
                ▼
       [Return Configuration]
```

### Hierarchical Configuration Loading (ConfigShowCommand)

```
[ConfigShowCommand::execute()]
                │
                ▼
  [Create HierarchicalConfigurationLoader]
                │
                ▼
     [Create ConfigurationHierarchy]
                │
                ▼
       [Analyze project structure]
                │
                ▼
     [Create ConfigurationDiscovery]
                │
                ▼
      [Scan for configuration files]
                │
                ▼
        [Parse each found file]
                │
                ▼
       [Collect parsing errors]
                │
                ▼
      [Create ConfigurationMerger]
                │
                ▼
      [Merge all configurations]
                │
                ▼
       [Detect conflicts]
                │
                ▼
        [Create source map]
                │
                ▼
     [Validate merged result]
                │
                ▼
    [Create EnhancedConfiguration]
                │
                ▼
      [Return with metadata]
```

## Data Flow Analysis

### Configuration Data Structure

```
quality-tools:
  project:                    ← Configuration::getProjectConfig()
    name: "project-name"     ← EnhancedConfiguration::getProjectName()
    php_version: "8.3"       ← Both classes duplicate this logic
    typo3_version: "13.4"    ← Both classes duplicate this logic

  paths:                      ← Configuration::getPathsConfig()
    scan: [...]              ← Configuration::getScanPaths()
    exclude: [...]           ← Configuration::getExcludePaths()

  tools:                      ← Configuration::getToolsConfig()
    rector:                  ← Configuration::getToolConfig('rector')
      enabled: true          ← Configuration::isToolEnabled('rector')
      paths:                 ← Configuration::getToolPaths('rector')
        scan: [...]
        exclude: [...]
```

### Source Mapping (EnhancedConfiguration Only)

```
source_map:
  "quality-tools.project.name": "project_root"
  "quality-tools.tools.rector.level": "config_dir"
  "quality-tools.tools.phpstan.memory_limit": "package_defaults"

conflicts:
  - key_path: "quality-tools.tools.rector.level"
    existing_source: "project_root"
    existing_value: "typo3-12"
    new_source: "config_dir"
    new_value: "typo3-13"
    resolution: "override"
```

## Class Relationship Matrix

| Class | YamlConfigurationLoader | HierarchicalConfigurationLoader | Configuration | EnhancedConfiguration |
|-------|------------------------|----------------------------------|---------------|----------------------|
| **YamlConfigurationLoader** | - | ❌ No relationship | ✅ Creates | ❌ No direct creation |
| **HierarchicalConfigurationLoader** | ❌ No relationship | - | ✅ Can create (via createSimpleConfiguration) | ✅ Creates |
| **Configuration** | ❌ Used by | ❌ Used by | - | ❌ No relationship |
| **EnhancedConfiguration** | ❌ No relationship | ❌ Used by | ✅ Can convert from (via fromConfiguration) | - |

### Legend
- ✅ Creates/Returns this type
- ❌ No direct relationship
- 🔄 Can convert between types

## Overlapping Responsibilities Summary

### 🔴 High Overlap (Identical Implementation)
- `getProjectPhpVersion()`, `getProjectName()`, `getProjectTypo3Version()`
- `getScanPaths()`, `getExcludePaths()`, `getToolPaths()`
- `isToolEnabled()`, `getToolConfig()`

### 🟡 Medium Overlap (Similar Logic)
- Configuration validation (both loaders)
- Security service usage (both loaders)
- File system operations (both loaders)

### 🟢 Low Overlap (Different Purposes)
- Path resolution (Configuration only)
- Source tracking (EnhancedConfiguration only)
- Conflict detection (EnhancedConfiguration only)
