# Configuration Reference

This document provides a complete reference for all configuration options available in the unified YAML configuration system.

## Configuration File Locations

The configuration loader searches for configuration files in the following order:

1. `.quality-tools.yaml` (preferred)
2. `quality-tools.yaml` (alternative)
3. `quality-tools.yml` (alternative)

## Configuration Hierarchy

Configuration is merged in the following order (later values override earlier ones):

1. **Package defaults** (built-in defaults)
2. **Global user configuration** (`~/.quality-tools.yaml`)
3. **Project-specific configuration** (project root)
4. **Command-line overrides** (--config, --path, etc.)

## Complete Configuration Schema

```yaml
quality-tools:
  # Project Information
  project:
    name: string                    # Project name (optional)
    php_version: string             # PHP version (e.g., "8.3")
    typo3_version: string           # TYPO3 version (e.g., "13.4")

  # Path Configuration
  paths:
    scan:                           # Directories to analyze
      - string                      # Path relative to project root
    exclude:                        # Directories to exclude
      - string                      # Path relative to project root

  # Tool Configuration
  tools:
    rector:
      enabled: boolean              # Enable/disable Rector
      level: string                 # Rector level: "typo3-13", "typo3-12", "typo3-11"
      php_version: string           # Override project PHP version
      dry_run: boolean              # Always run in dry-run mode

    fractor:
      enabled: boolean              # Enable/disable Fractor
      indentation: integer          # Indentation spaces (1-8)
      skip_files:                   # Files to skip
        - string                    # File pattern

    phpstan:
      enabled: boolean              # Enable/disable PHPStan
      level: integer                # Analysis level (0-9)
      memory_limit: string          # Memory limit (e.g., "1G", "512M")
      paths:                        # Custom paths to analyze
        - string                    # Path relative to project root

    php-cs-fixer:
      enabled: boolean              # Enable/disable PHP CS Fixer
      preset: string                # Preset: "typo3", "psr12", "symfony"
      cache: boolean                # Enable caching

    typoscript-lint:
      enabled: boolean              # Enable/disable TypoScript Lint
      indentation: integer          # Indentation spaces (1-8)
      ignore_patterns:              # Patterns to ignore
        - string                    # Pattern

  # Output Configuration
  output:
    verbosity: string               # "quiet", "normal", "verbose", "debug"
    colors: boolean                 # Enable colored output
    progress: boolean               # Show progress indicators

  # Performance Configuration
  performance:
    parallel: boolean               # Enable parallel processing
    max_processes: integer          # Maximum processes (1-16)
    cache_enabled: boolean          # Enable caching
```

## Configuration Sections

### Project Section

The `project` section defines basic project information that affects tool behavior.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | null | Project name for identification |
| `php_version` | string | "8.3" | Target PHP version (affects Rector rules) |
| `typo3_version` | string | "13.4" | Target TYPO3 version (affects tool rules) |

**Example:**
```yaml
quality-tools:
  project:
    name: "my-typo3-extension"
    php_version: "8.3"
    typo3_version: "13.4"
```

### Paths Section

The `paths` section defines which directories to scan and exclude during analysis.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `scan` | array | ["packages/", "config/system/"] | Directories to analyze |
| `exclude` | array | ["var/", "vendor/", "node_modules/"] | Directories to exclude |

**Default Scan Paths for Different Project Types:**

- **TYPO3 Extension**: `["Classes/", "Configuration/", "Tests/"]`
- **Site Package**: `["packages/", "config/"]`
- **Distribution**: `["packages/", "config/system/", "config/sites/"]`

**Example:**
```yaml
quality-tools:
  paths:
    scan:
      - "packages/"
      - "config/system/"
      - "config/sites/"
    exclude:
      - "var/"
      - "vendor/"
      - "public/"
      - ".build/"
```

### Tools Section

Each tool can be individually configured with its specific options.

#### Rector Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable Rector |
| `level` | string | "typo3-13" | Rector level (typo3-13, typo3-12, typo3-11) |
| `php_version` | string | project.php_version | Override PHP version for rules |
| `dry_run` | boolean | false | Always run in dry-run mode |

**Example:**
```yaml
quality-tools:
  tools:
    rector:
      enabled: true
      level: "typo3-13"
      php_version: "8.4"
      dry_run: false
```

#### Fractor Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable Fractor |
| `indentation` | integer | 2 | Number of spaces for indentation (1-8) |
| `skip_files` | array | [] | File patterns to skip |

**Example:**
```yaml
quality-tools:
  tools:
    fractor:
      enabled: true
      indentation: 2
      skip_files:
        - "*/Legacy/*"
        - "Configuration/TCA/Overrides/*"
```

#### PHPStan Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable PHPStan |
| `level` | integer | 6 | Analysis level (0-9, higher = stricter) |
| `memory_limit` | string | "1G" | Memory limit (e.g., "512M", "2G") |
| `paths` | array | paths.scan | Custom paths to analyze |

**Example:**
```yaml
quality-tools:
  tools:
    phpstan:
      enabled: true
      level: 8
      memory_limit: "2G"
      paths:
        - "packages/"
        - "Tests/"
```

#### PHP CS Fixer Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable PHP CS Fixer |
| `preset` | string | "typo3" | Code style preset (typo3, psr12, symfony) |
| `cache` | boolean | true | Enable result caching |

**Example:**
```yaml
quality-tools:
  tools:
    php-cs-fixer:
      enabled: true
      preset: "typo3"
      cache: true
```

#### TypoScript Lint Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable TypoScript Lint |
| `indentation` | integer | 2 | Number of spaces for indentation (1-8) |
| `ignore_patterns` | array | [] | File patterns to ignore |

**Example:**
```yaml
quality-tools:
  tools:
    typoscript-lint:
      enabled: true
      indentation: 2
      ignore_patterns:
        - "*.backup"
        - "*/Legacy/*"
```

### Output Section

Control the output behavior of all tools.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `verbosity` | string | "normal" | Output verbosity (quiet, normal, verbose, debug) |
| `colors` | boolean | true | Enable colored output |
| `progress` | boolean | true | Show progress indicators |

**Example:**
```yaml
quality-tools:
  output:
    verbosity: "verbose"
    colors: true
    progress: true
```

### Performance Section

Configure performance-related settings.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `parallel` | boolean | true | Enable parallel processing |
| `max_processes` | integer | 4 | Maximum parallel processes (1-16) |
| `cache_enabled` | boolean | true | Enable result caching |

**Example:**
```yaml
quality-tools:
  performance:
    parallel: true
    max_processes: 8
    cache_enabled: true
```

## Environment Variables

All configuration values support environment variable interpolation using the `${VAR:-default}` syntax:

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-my-project}"
    php_version: "${PHP_VERSION:-8.3}"
  
  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"
      memory_limit: "${PHPSTAN_MEMORY:-1G}"
```

### Environment Variable Syntax

- `${VAR}` - Required variable (fails if not set)
- `${VAR:-default}` - Optional variable with default value
- `${VAR:default}` - Alternative syntax (same as above)

### Type Safety

Environment variables are automatically converted to the correct type:

- Strings remain as strings
- Integers are converted from string to int
- Booleans accept: "true"/"false", "1"/"0", "yes"/"no"

## Validation

All configuration files are validated against a JSON Schema. Common validation errors include:

- **Invalid tool names**: Only recognized tools are allowed
- **Invalid option values**: Enum values must match exactly
- **Type mismatches**: Numbers must be numbers, booleans must be booleans
- **Range violations**: Values must be within allowed ranges
- **Pattern violations**: Strings must match required patterns

Use `qt config:validate` to check your configuration for errors.

## Complete Examples

### Minimal Configuration
```yaml
quality-tools:
  project:
    name: "my-project"
```

### TYPO3 Extension Configuration
```yaml
quality-tools:
  project:
    name: "my-extension"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "Classes/"
      - "Configuration/"
      - "Tests/"
    exclude:
      - "vendor/"
      - ".build/"

  tools:
    phpstan:
      level: 8
      memory_limit: "512M"
    
  performance:
    parallel: false
```

### Site Package Configuration
```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-site-package}"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/"

  tools:
    rector:
      level: "typo3-13"
    fractor:
      indentation: 2
    phpstan:
      level: 6
      memory_limit: "${PHPSTAN_MEMORY:-1G}"

  performance:
    parallel: true
    max_processes: 4
```

### Development Environment Configuration
```yaml
quality-tools:
  project:
    name: "dev-environment"

  tools:
    rector:
      dry_run: true  # Always dry-run in development
    phpstan:
      level: 5       # Lower level for faster development

  output:
    verbosity: "verbose"
    progress: true

  performance:
    parallel: false  # Easier debugging without parallel processing
```

### CI/CD Configuration
```yaml
quality-tools:
  project:
    name: "${CI_PROJECT_NAME}"
    php_version: "${PHP_VERSION}"

  output:
    verbosity: "normal"
    colors: false    # No colors in CI logs
    progress: false  # No progress bars in CI

  performance:
    parallel: true
    max_processes: "${CI_MAX_PROCESSES:-8}"
    cache_enabled: false  # Fresh analysis in CI
```