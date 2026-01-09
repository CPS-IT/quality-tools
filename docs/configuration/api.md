# Configuration API Documentation

This document provides comprehensive API documentation for the configuration system classes, intended for developers who want to extend or integrate with the unified YAML configuration system.

## Architecture Overview

The configuration system consists of several key classes that work together:

```
YamlConfigurationLoader
├── Loads and merges YAML configurations from multiple sources
├── Handles environment variable interpolation
└── Uses ConfigurationValidator for validation

Configuration
├── Holds the resolved configuration data
├── Provides typed access methods for all settings
└── Supports merging and default value handling

ConfigurationValidator
├── Validates configuration against JSON Schema
└── Provides detailed error reporting

ValidationResult
└── Contains validation results and error messages
```

## Core Classes

### Configuration Class

**Location:** `src/Configuration/Configuration.php`

The central class that holds and provides access to configuration data.

#### Constructor

```php
public function __construct(array $data = [])
```

**Parameters:**
- `$data` (array): Configuration data array, typically from YAML file

**Example:**
```php
$config = new Configuration([
    'quality-tools' => [
        'project' => [
            'name' => 'my-project',
            'php_version' => '8.3'
        ]
    ]
]);
```

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

**Returns:** array - Array of directory paths (default: ["var/", "vendor/", "node_modules/"])

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

---

```php
public static function createDefault(): self
```
Creates a configuration instance with default values.

**Returns:** Configuration - Default configuration instance

### YamlConfigurationLoader Class

**Location:** `src/Configuration/YamlConfigurationLoader.php`

Handles loading and merging YAML configuration files from multiple sources.

#### Constructor

```php
public function __construct(?ConfigurationValidator $validator = null)
```

**Parameters:**
- `$validator` (ConfigurationValidator|null): Optional validator instance

#### Main Methods

```php
public function load(string $projectRoot): Configuration
```
Loads and merges configuration from all sources.

**Parameters:**
- `$projectRoot` (string): Path to project root directory

**Returns:** Configuration - Merged configuration instance

**Throws:**
- `RuntimeException` - If configuration loading or validation fails

**Configuration Loading Order:**
1. Package defaults (lowest priority)
2. Global user configuration (`~/.quality-tools.yaml`)
3. Project configuration (project root)
4. CLI overrides (highest priority)

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

#### Internal Methods

```php
private function loadConfigurationHierarchy(string $projectRoot): array
```
Loads and merges configuration from all hierarchy levels.

---

```php
private function loadGlobalConfiguration(): array
```
Loads global user configuration from home directory.

---

```php
private function loadProjectConfiguration(string $projectRoot): array
```
Loads project-specific configuration.

---

```php
private function loadYamlFile(string $path): array
```
Loads and processes a single YAML file.

**Features:**
- Environment variable interpolation
- JSON Schema validation
- Error handling with detailed messages

---

```php
private function interpolateEnvironmentVariables(string $content): string
```
Performs environment variable interpolation on YAML content.

**Supported Syntax:**
- `${VAR}` - Required variable
- `${VAR:-default}` - Variable with default value

---

```php
private function mergeConfigurations(array $configurations): array
```
Merges multiple configuration arrays with precedence.

---

```php
private function deepMerge(array $array1, array $array2): array
```
Performs deep merge of configuration arrays.

### ConfigurationValidator Class

**Location:** `src/Configuration/ConfigurationValidator.php`

Validates configuration data against a JSON Schema.

#### Constructor

```php
public function __construct()
```

Initializes the validator with the built-in configuration schema.

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

#### Schema Structure

The validator uses a comprehensive JSON Schema that defines:

- **Project section**: name, php_version, typo3_version
- **Paths section**: scan, exclude arrays
- **Tools section**: Configuration for each tool (rector, fractor, phpstan, php-cs-fixer, typoscript-lint)
- **Output section**: verbosity, colors, progress
- **Performance section**: parallel, max_processes, cache_enabled

**Validation Features:**
- Type checking (string, integer, boolean, array)
- Pattern validation (version numbers, memory limits)
- Enum validation (predefined values)
- Range validation (minimum/maximum values)
- Required field validation

### ValidationResult Class

**Location:** `src/Configuration/ValidationResult.php`

Contains the result of configuration validation.

#### Constructor

```php
public function __construct(bool $isValid, array $errors = [])
```

**Parameters:**
- `$isValid` (bool): Whether validation passed
- `$errors` (array): Array of error messages

#### Methods

```php
public function isValid(): bool
```
Returns whether validation was successful.

**Returns:** bool - true if configuration is valid

---

```php
public function getErrors(): array
```
Returns array of validation error messages.

**Returns:** array - Error message strings

## Usage Examples

### Basic Configuration Loading

```php
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;

$loader = new YamlConfigurationLoader();
$config = $loader->load('/path/to/project');

// Access configuration
echo "Project: " . $config->getProjectName() . "\n";
echo "PHP Version: " . $config->getProjectPhpVersion() . "\n";
echo "PHPStan Level: " . $config->getPhpStanConfig()['level'] . "\n";
```

### Manual Configuration Creation

```php
use Cpsit\QualityTools\Configuration\Configuration;

$configData = [
    'quality-tools' => [
        'project' => [
            'name' => 'my-project',
            'php_version' => '8.4'
        ],
        'tools' => [
            'phpstan' => [
                'level' => 8,
                'memory_limit' => '2G'
            ]
        ]
    ]
];

$config = new Configuration($configData);
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

### Configuration Merging

```php
use Cpsit\QualityTools\Configuration\Configuration;

// Base configuration
$baseConfig = Configuration::createDefault();

// Override configuration
$overrideData = [
    'quality-tools' => [
        'tools' => [
            'phpstan' => [
                'level' => 9
            ]
        ]
    ]
];
$overrideConfig = new Configuration($overrideData);

// Merge configurations
$finalConfig = $baseConfig->merge($overrideConfig);
echo "Final PHPStan level: " . $finalConfig->getPhpStanConfig()['level']; // 9
```

### Environment Variable Handling

```php
use Cpsit\QualityTools\Configuration\YamlConfigurationLoader;

// Set environment variables
putenv('PROJECT_NAME=my-env-project');
putenv('PHPSTAN_LEVEL=7');

// YAML content with environment variables:
// quality-tools:
//   project:
//     name: "${PROJECT_NAME:-default}"
//   tools:
//     phpstan:
//       level: "${PHPSTAN_LEVEL:-6}"

$loader = new YamlConfigurationLoader();
$config = $loader->load('/path/to/project');

echo $config->getProjectName(); // "my-env-project"
echo $config->getPhpStanConfig()['level']; // 7
```

## Extension Points

### Custom Validation

Extend the validation system for custom requirements:

```php
class CustomConfigurationValidator extends ConfigurationValidator
{
    public function validate(array $config): ValidationResult
    {
        // Call parent validation first
        $result = parent::validate($config);

        if (!$result->isValid()) {
            return $result;
        }

        // Add custom validation logic
        $errors = [];
        $customErrors = $this->validateCustomRules($config);

        return new ValidationResult(empty($customErrors), $customErrors);
    }

    private function validateCustomRules(array $config): array
    {
        $errors = [];

        // Example: Ensure PHPStan level is not too high for large projects
        $phpstan = $config['quality-tools']['tools']['phpstan'] ?? [];
        if (($phpstan['level'] ?? 0) > 6) {
            // Check project size or complexity
            $errors[] = "PHPStan level too high for this project type";
        }

        return $errors;
    }
}
```

### Custom Configuration Loading

Create custom configuration loaders:

```php
class DatabaseConfigurationLoader
{
    public function load(string $projectId): Configuration
    {
        // Load configuration from database
        $configData = $this->loadFromDatabase($projectId);

        // Apply defaults and validation
        $validator = new ConfigurationValidator();
        $result = $validator->validate($configData);

        if (!$result->isValid()) {
            throw new RuntimeException('Invalid configuration: ' . implode(', ', $result->getErrors()));
        }

        return new Configuration($configData);
    }

    private function loadFromDatabase(string $projectId): array
    {
        // Database loading logic
        return [];
    }
}
```

## Error Handling

The configuration system provides comprehensive error handling:

### Common Exceptions

1. **RuntimeException** - Configuration loading errors
2. **RuntimeException** - Environment variable errors
3. **RuntimeException** - YAML parsing errors
4. **RuntimeException** - Validation errors

### Error Examples

```php
try {
    $loader = new YamlConfigurationLoader();
    $config = $loader->load('/invalid/path');
} catch (RuntimeException $e) {
    // Handle specific error types
    if (str_contains($e->getMessage(), 'Environment variable')) {
        echo "Environment variable error: " . $e->getMessage();
    } elseif (str_contains($e->getMessage(), 'Invalid configuration')) {
        echo "Validation error: " . $e->getMessage();
    } else {
        echo "Loading error: " . $e->getMessage();
    }
}
```

## Performance Considerations

### Caching Configuration

For performance-critical applications, consider caching parsed configurations:

```php
class CachedConfigurationLoader extends YamlConfigurationLoader
{
    private array $cache = [];

    public function load(string $projectRoot): Configuration
    {
        $cacheKey = md5($projectRoot);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $config = parent::load($projectRoot);
        $this->cache[$cacheKey] = $config;

        return $config;
    }
}
```

### Lazy Loading

For applications that don't always need configuration:

```php
class LazyConfiguration
{
    private ?Configuration $config = null;
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function getConfig(): Configuration
    {
        if ($this->config === null) {
            $loader = new YamlConfigurationLoader();
            $this->config = $loader->load($this->projectRoot);
        }

        return $this->config;
    }
}
```

This API documentation provides a complete reference for developers working with the configuration system. The classes are designed to be extensible while maintaining backward compatibility and robust error handling.
