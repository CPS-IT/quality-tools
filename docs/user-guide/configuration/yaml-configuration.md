# YAML Configuration Guide

This guide walks you through setting up and using the unified YAML configuration system for CPSIT Quality Tools.

## Overview

The unified YAML configuration system provides a single file to configure all quality analysis tools instead of managing separate configuration files for each tool. It offers:

- **Centralized Configuration**: One file controls all tools
- **Environment Support**: Use environment variables for flexible deployments
- **Project Templates**: Quick setup with predefined configurations
- **Schema Validation**: Automatic validation with helpful error messages
- **Configuration Hierarchy**: Merge settings from multiple sources

## Getting Started

### Step 1: Initialize Configuration

Create your first configuration file using one of the available templates:

```bash
# For TYPO3 extensions
vendor/bin/qt config:init --template=typo3-extension

# For TYPO3 site packages
vendor/bin/qt config:init --template=typo3-site-package

# For TYPO3 distributions
vendor/bin/qt config:init --template=typo3-distribution

# For general projects
vendor/bin/qt config:init --template=default
```

This creates a `.quality-tools.yaml` file in your project root with sensible defaults.

### Step 2: Customize Your Configuration

Edit the generated `.quality-tools.yaml` file to match your project needs:

```yaml
# Basic project configuration
quality-tools:
  project:
    name: "my-awesome-project"
    php_version: "8.3"
    typo3_version: "13.4"

  # Define which directories to analyze
  paths:
    scan:
      - "packages/"
      - "config/system/"
    exclude:
      - "var/"
      - "vendor/"
      - "node_modules/"

  # Configure individual tools
  tools:
    rector:
      enabled: true
      level: "typo3-13"
    
    phpstan:
      enabled: true
      level: 6
      memory_limit: "1G"
    
    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  # Control output behavior
  output:
    verbosity: "normal"
    colors: true
    progress: true
```

### Step 3: Validate Your Configuration

Check that your configuration is valid:

```bash
vendor/bin/qt config:validate
```

If there are any issues, the command will show detailed error messages to help you fix them.

### Step 4: View Resolved Configuration

See how your configuration is resolved after merging all sources:

```bash
# View as YAML
vendor/bin/qt config:show

# View as JSON
vendor/bin/qt config:show --format=json

# Show configuration sources (verbose)
vendor/bin/qt config:show --verbose
```

### Step 5: Run Quality Tools

With your configuration in place, run the quality tools:

```bash
# Run analysis tools
vendor/bin/qt lint:rector
vendor/bin/qt lint:phpstan
vendor/bin/qt lint:php-cs-fixer

# Apply fixes
vendor/bin/qt fix:rector
vendor/bin/qt fix:php-cs-fixer
```

## Configuration Hierarchy

The configuration system merges settings from multiple sources in this order:

1. **Package Defaults** (built-in)
2. **Global User Config** (`~/.quality-tools.yaml`)
3. **Project Config** (`.quality-tools.yaml` in project root)
4. **CLI Overrides** (command-line options)

### Example: Global User Configuration

Create `~/.quality-tools.yaml` to set defaults for all your projects:

```yaml
quality-tools:
  # Your personal preferences
  output:
    colors: true
    verbosity: "verbose"

  performance:
    parallel: true
    max_processes: 8

  tools:
    phpstan:
      memory_limit: "2G"  # You have plenty of RAM
```

### Example: Project-Specific Overrides

Your project's `.quality-tools.yaml` can override global settings:

```yaml
quality-tools:
  project:
    name: "legacy-project"

  tools:
    phpstan:
      level: 4  # Lower level for legacy code
      memory_limit: "512M"  # Override global setting

    rector:
      enabled: false  # Skip Rector for this project
```

## Working with Environment Variables

Use environment variables to make your configuration flexible across different environments.

### Basic Environment Variable Usage

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

### Environment-Specific Configuration

#### Development Environment
```bash
export PROJECT_NAME="my-project-dev"
export PHPSTAN_LEVEL="5"
export PHPSTAN_MEMORY="512M"
```

#### CI/CD Environment
```bash
export PROJECT_NAME="my-project-ci"
export PHPSTAN_LEVEL="8"
export PHPSTAN_MEMORY="2G"
```

#### Production Analysis
```bash
export PROJECT_NAME="my-project-prod"
export PHPSTAN_LEVEL="6"
export PHPSTAN_MEMORY="4G"
```

## Common Configuration Patterns

### Pattern 1: Extension Development

Perfect for TYPO3 extension development:

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
      - "Documentation/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    phpstan:
      enabled: true
      level: 8  # Strict analysis for extensions
      memory_limit: "512M"

    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  # Single-threaded for smaller extensions
  performance:
    parallel: false
```

### Pattern 2: Site Package Development

Configuration for TYPO3 site packages:

```yaml
quality-tools:
  project:
    name: "client-site-package"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/"
    exclude:
      - "var/"
      - "vendor/"
      - "public/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    fractor:
      enabled: true
      indentation: 2

    phpstan:
      enabled: true
      level: 6
      memory_limit: "1G"

    typoscript-lint:
      enabled: true
      indentation: 2

  performance:
    parallel: true
    max_processes: 4
```

### Pattern 3: Large Distribution

Configuration for large TYPO3 distributions:

```yaml
quality-tools:
  project:
    name: "enterprise-distribution"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/system/"
      - "config/sites/"
    exclude:
      - "var/"
      - "vendor/"
      - "public/"
      - "node_modules/"
      - ".build/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    phpstan:
      enabled: true
      level: 5  # Moderate level for large codebase
      memory_limit: "2G"  # More memory for large projects

    php-cs-fixer:
      enabled: true
      preset: "typo3"
      cache: true

  performance:
    parallel: true
    max_processes: 8  # More processes for large projects
    cache_enabled: true
```

### Pattern 4: CI/CD Configuration

Optimized for continuous integration:

```yaml
quality-tools:
  project:
    name: "${CI_PROJECT_NAME:-ci-project}"

  tools:
    rector:
      enabled: true
      dry_run: true  # Always dry-run in CI

    phpstan:
      enabled: true
      level: "${PHPSTAN_LEVEL:-8}"
      memory_limit: "${CI_MEMORY_LIMIT:-2G}"

  output:
    colors: false  # No colors in CI logs
    progress: false  # No progress bars in CI
    verbosity: "normal"

  performance:
    parallel: true
    max_processes: "${CI_PROCESSORS:-4}"
    cache_enabled: false  # Fresh analysis each time
```

## Advanced Features

### Disabling Tools Conditionally

```yaml
quality-tools:
  tools:
    rector:
      enabled: "${ENABLE_RECTOR:-true}"
    
    phpstan:
      enabled: "${ENABLE_PHPSTAN:-true}"
      level: "${PHPSTAN_LEVEL:-6}"
```

### Tool-Specific Path Overrides

```yaml
quality-tools:
  paths:
    scan:
      - "packages/"

  tools:
    phpstan:
      paths:  # PHPStan-specific paths
        - "packages/"
        - "Tests/"
    
    typoscript-lint:
      # Uses global scan paths
```

### Memory Optimization

```yaml
quality-tools:
  tools:
    phpstan:
      memory_limit: "${PHPSTAN_MEMORY:-1G}"
    
  performance:
    parallel: "${ENABLE_PARALLEL:-true}"
    max_processes: "${MAX_PROCESSES:-4}"
```

## Troubleshooting

### Common Issues

1. **Configuration file not found**
   ```bash
   # Check current directory
   ls -la .quality-tools.yaml
   
   # Initialize if missing
   vendor/bin/qt config:init
   ```

2. **Validation errors**
   ```bash
   # Check validation
   vendor/bin/qt config:validate
   
   # Show resolved config
   vendor/bin/qt config:show --verbose
   ```

3. **Environment variables not working**
   ```bash
   # Check environment variables
   echo $PROJECT_NAME
   
   # Test variable substitution
   vendor/bin/qt config:show | grep -A5 "project:"
   ```

### Debugging Configuration

Use these commands to debug configuration issues:

```bash
# Show all configuration sources
vendor/bin/qt config:show --verbose

# Validate configuration
vendor/bin/qt config:validate

# Show configuration in JSON format
vendor/bin/qt config:show --format=json
```

### Getting Help

- Check the [Configuration Reference](reference.md) for all available options
- See the [Migration Guide](migration.md) for converting existing configurations
- Review [Environment Variables](environment-variables.md) for advanced variable usage

## Next Steps

- [Explore all configuration options](reference.md)
- [Learn about project templates](templates.md)
- [Set up environment-specific configurations](environment-variables.md)
- [Migrate from existing tool configs](migration.md)