# Configuration API Documentation

This document provides API documentation for the configuration system classes, intended for developers who want to extend or integrate with the unified YAML configuration system.

## Architecture Overview

The configuration system consists of several key classes that work together:

```
ConfigurationLoader
- Loads and merges YAML configurations from multiple sources
- Handles environment variable interpolation
- Uses ConfigurationValidator for validation
- Delegates to ConfigurationDiscovery, ConfigurationHierarchy, ConfigurationMerger

Configuration
- Holds the resolved configuration data
- Provides typed access methods for all settings
- Supports path resolution via PathResolutionService
- Supports vendor directory detection via VendorDirectoryDetector

ConfigurationValidator
- Validates configuration against JSON Schema
- Provides detailed error reporting

ConfigurationBuilder
- Builds tool-specific configurations (Rector, Fractor, etc.)
- Resolves paths using PathResolutionService

ValidationResult
- Contains validation results and error messages
```

## Core Classes

### Configuration Class

**Location:** `src/Configuration/Configuration.php`

The central class that holds and provides access to configuration data. It unifies the functionality previously split across `SimpleConfiguration` and `EnhancedConfiguration`.

#### Constructor

```php
public function __construct(
    private string $projectRoot,
    private array $data = [],
    private array $sourceMap = [],
    private array $conflicts = [],
    private array $mergeSummary = [],
    private bool $hierarchicalMode = false,
    private ?ConfigurationValidator $validator = null,
    private ?ProjectConfigService $projectConfigService = null,
    private ?ToolConfigService $toolConfigService = null,
    private ?PathResolutionService $pathResolutionService = null,
    private ?ConfigurationHierarchy $hierarchy = null,
    private ?ConfigurationDiscovery $discovery = null,
)
```

**Parameters:**
- `$projectRoot` (string): Absolute path to the project root directory (immutable after construction)
- `$data` (array): Configuration data array, typically from YAML file
- `$sourceMap` (array): Tracks which configuration source provided each value
- `$conflicts` (array): Records configuration conflicts during merging
- `$mergeSummary` (array): Summary of how configurations were merged
- `$hierarchicalMode` (bool): Whether hierarchical configuration merging was used
- `$validator` (ConfigurationValidator|null): Optional validator instance
- `$projectConfigService` (ProjectConfigService|null): Optional project config service
- `$toolConfigService` (ToolConfigService|null): Optional tool config service
- `$pathResolutionService` (PathResolutionService|null): Optional path resolution service
- `$hierarchy` (ConfigurationHierarchy|null): Optional hierarchy definition
- `$discovery` (ConfigurationDiscovery|null): Optional configuration discovery

#### Project Configuration Methods

```php
public function getProjectPhpVersion(): string
```
Returns the target PHP version for the project.

**Returns:** string - PHP version (default: "8.3")

---

```php
public function getProjectTypo3Version(): string
```
Returns the target TYPO3 version for the project.

**Returns:** string - TYPO3 version (default: "13.4")

---

```php
public function getProjectName(): ?string
```
Returns the project name if configured.

**Returns:** string|null - Project name or null if not set

---

```php
public function getProjectRoot(): string
```
Returns the project root directory path.

**Returns:** string - Absolute path to the project root

#### Path Configuration Methods

```php
public function getScanPaths(): array
```
Returns directories to scan during analysis.

**Returns:** array - Array of directory paths (default: ["packages/", "config/system/"])

---

```php
public function getExcludePaths(): array
```
Returns directories to exclude from analysis.

**Returns:** array - Array of directory paths (default: ["var/", "vendor/", "node_modules/", ...])

---

```php
public function getToolPaths(string $tool): array
```
Returns tool-specific paths from the configuration.

**Parameters:**
- `$tool` (string): Tool name (rector, phpstan, php-cs-fixer, fractor, typoscript-lint)

**Returns:** array - Tool-specific paths (empty if none configured)

---

```php
public function getResolvedPathsForTool(string $tool): array
```
Returns resolved absolute paths for a specific tool, using PathResolutionService to expand glob patterns and validate directories.

**Parameters:**
- `$tool` (string): Tool name

**Returns:** array - Array of resolved absolute paths

#### Vendor Directory Methods

```php
public function hasVendorDirectory(): bool
```
Returns whether a vendor directory was detected.

**Returns:** bool - true if vendor directory exists

---

```php
public function getVendorPath(): ?string
```
Returns the detected vendor directory path.

**Returns:** string|null - Absolute path to vendor directory, or null

---

```php
public function getVendorBinPath(): ?string
```
Returns the vendor bin directory path.

**Returns:** string|null - Absolute path to vendor/bin directory, or null

#### Tool Configuration Methods

```php
public function isToolEnabled(string $tool): bool
```
Checks if a specific tool is enabled.

**Parameters:**
- `$tool` (string): Tool name (rector, phpstan, php-cs-fixer, fractor, typoscript-lint)

**Returns:** bool - true if enabled (default: true)

---

```php
public function getToolConfig(string $tool): array
```
Returns the complete configuration for a specific tool.

**Parameters:**
- `$tool` (string): Tool name

**Returns:** array - Tool configuration array

#### Tool-Specific Configuration Methods

```php
public function getRectorConfig(): array
```
Returns Rector-specific configuration with defaults applied.

**Returns:** array with keys:
- `enabled` (bool): Whether Rector is enabled
- `level` (string): Rector level ("typo3-13", "typo3-12", "typo3-11")
- `php_version` (string): PHP version for rules

---

```php
public function getFractorConfig(): array
```
Returns Fractor-specific configuration with defaults applied.

**Returns:** array with keys:
- `enabled` (bool): Whether Fractor is enabled
- `indentation` (int): Number of spaces for indentation

---

```php
public function getPhpStanConfig(): array
```
Returns PHPStan-specific configuration with defaults applied.

**Returns:** array with keys:
- `enabled` (bool): Whether PHPStan is enabled
- `level` (int): Analysis level (0-9)
- `memory_limit` (string): Memory limit

---

```php
public function getPhpCsFixerConfig(): array
```
Returns PHP CS Fixer-specific configuration with defaults applied.

**Returns:** array with keys:
- `enabled` (bool): Whether PHP CS Fixer is enabled
- `preset` (string): Code style preset

---

```php
public function getTypoScriptLintConfig(): array
```
Returns TypoScript Lint-specific configuration with defaults applied.

**Returns:** array with keys:
- `enabled` (bool): Whether TypoScript Lint is enabled
- `indentation` (int): Number of spaces for indentation

#### Output Configuration Methods

```php
public function getVerbosity(): string
```
Returns output verbosity level.

**Returns:** string - One of: "quiet", "normal", "verbose", "debug" (default: "normal")

---

```php
public function isColorsEnabled(): bool
```
Returns whether colored output is enabled.

**Returns:** bool - true if colors enabled (default: true)

---

```php
public function isProgressEnabled(): bool
```
Returns whether progress indicators are enabled.

**Returns:** bool - true if progress enabled (default: true)

#### Performance Configuration Methods

```php
public function isParallelEnabled(): bool
```
Returns whether parallel processing is enabled.

**Returns:** bool - true if parallel enabled (default: true)

---

```php
public function getMaxProcesses(): int
```
Returns the maximum number of parallel processes.

**Returns:** int - Number of processes (default: 4)

---

```php
public function isCacheEnabled(): bool
```
Returns whether result caching is enabled.

**Returns:** bool - true if caching enabled (default: true)

#### Debug Methods

```php
public function getPathScanningDebugInfo(string $tool): array
```
Returns debug information about path resolution for a specific tool.

**Parameters:**
- `$tool` (string): Tool name

**Returns:** array with keys:
- `tool` (string): The tool name
- `project_root` (string): The project root path
- `resolved_paths` (array): Resolved absolute paths
- `path_resolution_service` (array): PathResolutionService debug data

---

```php
public function getVendorDetectionDebugInfo(): array
```
Returns debug information about vendor directory detection.

**Returns:** array with keys:
- `project_root` (string): The project root path
- `vendor_path` (string|null): Detected vendor path
- `vendor_bin_path` (string|null): Detected vendor bin path
- `detection_method` (string): How vendor directory was detected

#### Utility Methods

```php
public function toArray(): array
```
Returns the complete configuration as an array.

**Returns:** array - Complete configuration data

---

```php
public function merge(Configuration $other): self
```
Merges this configuration with another configuration.

**Parameters:**
- `$other` (Configuration): Configuration to merge

**Returns:** Configuration - New merged configuration instance

### ConfigurationLoader Class

**Location:** `src/Configuration/ConfigurationLoader.php`

Handles loading and merging YAML configuration files from multiple sources. This is the unified loader that replaced both `SimpleConfigurationLoader` and `HierarchicalConfigurationLoader`.

#### Constructor

```php
public function __construct(
    private ConfigurationValidator $validator,
    private SecurityService $securityService,
    private FilesystemService $filesystemService,
    private ToolConfigurationValidationService $toolValidator,
    private ?ProjectConfigService $projectConfigService = null,
    private ?ToolConfigService $toolConfigService = null,
    private ?PathResolutionService $pathResolutionService = null,
)
```

**Parameters:**
- `$validator` (ConfigurationValidator): Configuration schema validator
- `$securityService` (SecurityService): Security validation service
- `$filesystemService` (FilesystemService): Filesystem abstraction
- `$toolValidator` (ToolConfigurationValidationService): Tool configuration validator
- `$projectConfigService` (ProjectConfigService|null): Optional project config service
- `$toolConfigService` (ToolConfigService|null): Optional tool config service
- `$pathResolutionService` (PathResolutionService|null): Optional path resolution service

#### Main Methods

```php
public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
```
Loads and merges configuration from all sources.

**Parameters:**
- `$projectRoot` (string): Path to project root directory
- `$commandLineOverrides` (array): Optional command-line override values

**Returns:** ConfigurationInterface - Merged configuration instance

**Configuration Loading Order:**
1. Package defaults (lowest priority)
2. Global user configuration (`~/.quality-tools.yaml`)
3. Project configuration (project root)
4. CLI overrides (highest priority)

---

```php
public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
```
Loads configuration optimized for a specific tool.

**Parameters:**
- `$projectRoot` (string): Path to project root directory
- `$tool` (string): Tool name
- `$commandLineOverrides` (array): Optional command-line override values

**Returns:** ConfigurationInterface - Tool-optimized configuration instance

---

```php
public function findConfigurationFile(string $projectRoot): ?string
```
Finds the configuration file in the project root.

**Parameters:**
- `$projectRoot` (string): Path to project root directory

**Returns:** string|null - Path to configuration file or null if not found

**Search Order:**
1. `.quality-tools.yaml`
2. `quality-tools.yaml`
3. `quality-tools.yml`

---

```php
public function supportsConfiguration(string $projectRoot): bool
```
Checks if the project has a YAML configuration file.

**Parameters:**
- `$projectRoot` (string): Path to project root directory

**Returns:** bool - true if configuration file exists

#### Analysis Methods

```php
public function hasHierarchicalConfiguration(string $projectRoot): bool
```
Checks if the project uses hierarchical configuration (multiple sources).

---

```php
public function getConfigurationErrors(string $projectRoot): array
```
Returns any validation errors in the project's configuration.

---

```php
public function getConfigurationDebugInfo(string $projectRoot): array
```
Returns debug information about configuration loading.

---

```php
public function getConfigurationSources(string $projectRoot): array
```
Returns the list of configuration sources that were found.

---

```php
public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
```
Returns a preview of the merged configuration without creating a Configuration object.

#### Factory Methods

```php
public static function createSimpleLoader(
    SecurityService $securityService,
    FilesystemService $filesystemService,
): self
```
Creates a loader configured for simple (single-source) configuration.

---

```php
public static function createHierarchicalLoader(
    SecurityService $securityService,
    FilesystemService $filesystemService,
): self
```
Creates a loader configured for hierarchical (multi-source) configuration.

### ConfigurationValidator Class

**Location:** `src/Configuration/ConfigurationValidator.php`

Validates configuration data against a JSON Schema.

#### Methods

```php
public function validate(array $config): ValidationResult
```
Validates configuration data against the schema.

**Parameters:**
- `$config` (array): Configuration data to validate

**Returns:** ValidationResult - Validation result with errors if any

**Example:**
```php
$validator = new ConfigurationValidator();
$result = $validator->validate($configData);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        echo "Error: " . $error . "\n";
    }
}
```

### ConfigurationBuilder Class

**Location:** `src/Configuration/ConfigurationBuilder.php`

Builds tool-specific configurations based on resolved paths and project settings.

#### Constructor

```php
public function __construct(ConfigurationInterface $configuration)
```

#### Methods

```php
public function buildRectorConfiguration(): array
```
Builds Rector configuration with resolved paths.

**Returns:** array with keys: `paths`, `project_root`, `php_version`, etc.

---

```php
public function generateConfigurationFileContent(string $tool): string
```
Generates the content for a tool-specific configuration file.

**Parameters:**
- `$tool` (string): Tool name (rector, fractor)

**Returns:** string - PHP configuration file content

### ValidationResult Class

**Location:** `src/Configuration/ValidationResult.php`

Contains the result of configuration validation.

#### Methods

```php
public function isValid(): bool
```
Returns whether validation was successful.

---

```php
public function getErrors(): array
```
Returns array of validation error messages.

## Usage Examples

### Basic Configuration Loading

```php
use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Symfony\Component\Filesystem\Filesystem;

$securityService = new SecurityService();
$filesystemService = new FilesystemService(new Filesystem(), $securityService);

$loader = new ConfigurationLoader(
    new ConfigurationValidator(),
    $securityService,
    $filesystemService,
    new ToolConfigurationValidationService(),
);

$config = $loader->load('/path/to/project');

// Access configuration
echo "Project: " . $config->getProjectName() . "\n";
echo "PHP Version: " . $config->getProjectPhpVersion() . "\n";
echo "PHPStan Level: " . $config->getPhpStanConfig()['level'] . "\n";
```

### Loading with Path Resolution

```php
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;

$pathResolutionService = new PathResolutionService(
    $filesystemService,
    new VendorDirectoryDetector(),
);

$loader = new ConfigurationLoader(
    new ConfigurationValidator(),
    $securityService,
    $filesystemService,
    new ToolConfigurationValidationService(),
    pathResolutionService: $pathResolutionService,
);

$config = $loader->load('/path/to/project');

// Get resolved absolute paths for a tool
$rectorPaths = $config->getResolvedPathsForTool('rector');

// Check vendor directory
if ($config->hasVendorDirectory()) {
    echo "Vendor: " . $config->getVendorPath() . "\n";
}
```

### Configuration Validation

```php
use Cpsit\QualityTools\Configuration\ConfigurationValidator;

$validator = new ConfigurationValidator();
$result = $validator->validate($configData);

if (!$result->isValid()) {
    echo "Configuration errors:\n";
    foreach ($result->getErrors() as $error) {
        echo "- " . $error . "\n";
    }
} else {
    echo "Configuration is valid!\n";
}
```

### Building Tool Configurations

```php
use Cpsit\QualityTools\Configuration\ConfigurationBuilder;

$config = $loader->load('/path/to/project');
$builder = new ConfigurationBuilder($config);

// Build Rector configuration with resolved paths
$rectorConfig = $builder->buildRectorConfiguration();
// Returns: ['paths' => [...], 'project_root' => '...', 'php_version' => '8.3']

// Generate configuration file content
$rectorContent = $builder->generateConfigurationFileContent('rector');
$fractorContent = $builder->generateConfigurationFileContent('fractor');
```

### Environment Variable Handling

```php
// YAML content with environment variables:
// quality-tools:
//   project:
//     name: "${PROJECT_NAME:-default}"
//   tools:
//     phpstan:
//       level: "${PHPSTAN_LEVEL:-6}"

$loader = new ConfigurationLoader(
    new ConfigurationValidator(),
    $securityService,
    $filesystemService,
    new ToolConfigurationValidationService(),
);

$config = $loader->load('/path/to/project');

echo $config->getProjectName(); // value of $PROJECT_NAME or "default"
echo $config->getPhpStanConfig()['level']; // value of $PHPSTAN_LEVEL or 6
```

## Error Handling

The configuration system provides structured exception handling:

### Exception Classes

1. **ConfigurationFileNotFoundException** - Configuration file does not exist
2. **ConfigurationFileNotReadableException** - Configuration file exists but cannot be read
3. **ConfigurationLoadException** - General configuration loading errors
4. **InvalidArgumentException** - Invalid method arguments

### Error Examples

```php
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;

try {
    $config = $loader->load('/path/to/project');
} catch (ConfigurationFileNotReadableException $e) {
    echo "File permission error: " . $e->getMessage();
} catch (ConfigurationLoadException $e) {
    echo "Loading error: " . $e->getMessage();
}
```

When no configuration file is found, the loader returns a configuration with default values rather than throwing an exception.

This API documentation provides a reference for developers working with the configuration system. The classes are designed to be extensible while maintaining robust error handling.
