# Configuration

CPSIT Quality Tools can be customized using environment variables and runtime options. This guide covers all available configuration options.

## Environment Variables

### QT_PROJECT_ROOT

Override automatic TYPO3 project detection by specifying the project root directory.

**Usage:**
```bash
export QT_PROJECT_ROOT=/path/to/your/typo3/project
```

**Examples:**

```bash
# Unix/Linux/macOS - Temporary override
QT_PROJECT_ROOT=/var/www/mysite vendor/bin/qt --version

# Unix/Linux/macOS - Persistent override
export QT_PROJECT_ROOT=/var/www/mysite
vendor/bin/qt --version

# Windows - Temporary override
set QT_PROJECT_ROOT=C:\xampp\htdocs\mysite && vendor\bin\qt --version

# Windows - Persistent override
setx QT_PROJECT_ROOT "C:\xampp\htdocs\mysite"
vendor\bin\qt --version
```

**When to Use:**
- Automatic project detection fails
- Working with non-standard project structures
- Running from outside the project directory
- CI/CD pipeline integration
- Docker container environments

**Requirements:**
- Path must exist and be a directory
- The tool will use this path as the project root without TYPO3 validation

### QT_DEBUG

Enable debug mode to get detailed error information and execution traces.

**Usage:**
```bash
export QT_DEBUG=true
```

**Examples:**

```bash
# Temporary debug mode
QT_DEBUG=true vendor/bin/qt --version

# Persistent debug mode
export QT_DEBUG=true
vendor/bin/qt --version
```

**Debug Output Includes:**
- Detailed error messages
- Full exception stack traces
- Project detection steps
- File system traversal information
- Dependency validation results

**When to Use:**
- Troubleshooting project detection issues
- Investigating command failures
- Development and testing
- Bug reporting

## Command-Line Options

### Global Options

These options are available for all commands:

| Option      | Short  | Description                                     | Default  |
|-------------|--------|-------------------------------------------------|----------|
| `--help`    | `-h`   | Display help information                        | -        |
| `--version` | `-V`   | Display version information                     | -        |
| `--verbose` | `-v`   | Increase verbosity (can be used multiple times) | Normal   |
| `--quiet`   | `-q`   | Suppress output                                 | -        |

### Verbosity Levels

Control output detail with the verbose option:

```bash
# Normal output
vendor/bin/qt lint:rector

# Verbose output (-v)
vendor/bin/qt lint:rector -v

# Very verbose output (-vv)
vendor/bin/qt lint:rector -vv

# Debug level output (-vvv)
vendor/bin/qt lint:rector -vvv
```

**Verbosity Levels:**
- **Normal**: Standard output with essential information
- **Verbose (-v)**: Additional operational details
- **Very Verbose (-vv)**: Detailed process information
- **Debug (-vvv)**: Full debug information including internal state

### Quiet Mode

Suppress all output except errors:

```bash
# Quiet execution
vendor/bin/qt --version --quiet

# Combine with other options
vendor/bin/qt --quiet [command]
```

## Configuration Files

### Hierarchical Configuration System

The tool implements a hierarchical configuration override system that allows projects to customize quality tool configurations at multiple levels. This provides powerful configuration management while maintaining simplicity for basic use cases.

### Configuration Precedence Hierarchy

Configuration sources are applied in the following order (highest to lowest priority):

1. **Command Line Arguments** - Highest priority
2. **Project Root Configuration** - `quality-tools.yaml` in project root
3. **Config Directory Configuration** - `quality-tools.yaml` in `config/` directory
4. **Tool-Specific Project Configuration** - Tool config files in the project root
5. **Tool-Specific Config Directory** - Tool config files in `config/` directory
6. **Package Configuration** - `quality-tools.yaml` in package directories
7. **Global User Configuration** - `~/.quality-tools.yaml` in user's home directory
8. **Package Defaults** - Lowest priority

### YAML Configuration Files

The system looks for YAML configuration files in the following locations:

#### Global User Configuration
- `~/.quality-tools.yaml` (user's home directory)

#### Project Root
- `quality-tools.yaml`
- `.quality-tools.yaml`
- `quality-tools.yml`

#### Config Directory
- `config/quality-tools.yaml`
- `config/.quality-tools.yaml`
- `config/quality-tools.yml`

#### Package Directories
- `packages/*/quality-tools.yaml`
- `packages/*/.quality-tools.yaml`

### Tool-Specific Configuration Files

Tool-specific configuration files provide advanced configuration options for each tool. There are two ways to use them:

#### Auto-Discovery
The system automatically discovers tool configuration files in standard locations:

**Project Root:**
- `rector.php` (Rector)
- `phpstan.neon`, `phpstan.neon.dist` (PHPStan)
- `.php-cs-fixer.dist.php`, `.php-cs-fixer.php` (PHP CS Fixer)
- `typoscript-lint.yml` (TypoScript Lint)
- `fractor.php` (Fractor)

**Config Directory:**
- `config/rector.php`
- `config/phpstan.neon`
- `config/.php-cs-fixer.dist.php`
- `config/.php-cs-fixer.php`
- `config/typoscript-lint.yml`
- `config/fractor.php`

#### Custom Configuration Files
You can also specify custom configuration file paths in your `.quality-tools.yaml`:

```yaml
quality-tools:
  tools:
    rector:
      enabled: true
      config_file: "custom/my-rector-config.php"

    phpstan:
      enabled: true
      config_file: "config/custom/phpstan.neon"

    php-cs-fixer:
      enabled: true
      config_file: ".php-cs-fixer.custom.php"
```

#### Configuration Precedence
The system follows this precedence order (highest to lowest):
1. Command-line `--config` option
2. Custom `config_file` in YAML configuration
3. Auto-discovered tool configuration files
4. Tool settings in YAML configuration
5. Package default configurations

### Configuration Usage Examples

#### Global User Configuration

Create a global default configuration that applies to all your projects:

```yaml
# ~/.quality-tools.yaml
quality-tools:
  project:
    php_version: "8.4"
    typo3_version: "13.4"

  tools:
    phpstan:
      enabled: true
      level: 6
      memory_limit: "2G"

  paths:
    exclude:
      - "var/"
      - "vendor/"
      - ".git/"
```

#### Basic Project Configuration

Override global defaults for a specific project:

```yaml
# project-root/quality-tools.yaml
quality-tools:
  project:
    name: "my-project"
    php_version: "8.4"  # Override global default

  tools:
    rector:
      level: "typo3-13"
      enabled: true

    phpstan:
      level: 8  # Higher than global default

  paths:
    scan:
      - "packages/"
      - "src/"
    exclude:
      - "var/"
      - "public/"
```

#### Config Directory Overrides

Environment-specific or deployment-specific overrides:

```yaml
# project-root/config/quality-tools.yaml
quality-tools:
  tools:
    phpstan:
      level: 5  # Lower level for development

    php-cs-fixer:
      preset: "custom"

  paths:
    exclude:
      - "legacy/"  # Additional exclusion
```

#### Tool-Specific Configuration

When you need complex tool-specific configuration that goes beyond the unified YAML format:

```php
<?php
// project-root/rector.php
return RectorConfig::configure()
    ->withPaths(['src/', 'packages/'])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withRules([
        // Complex Rector-specific configuration
    ]);
```

### Environment Variable Interpolation

Configuration files support environment variable interpolation with default values:

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-default-project}"
    php_version: "${PHP_VERSION:-8.4}"

  tools:
    phpstan:
      memory_limit: "${PHPSTAN_MEMORY:-2G}"
      level: ${PHPSTAN_LEVEL:-6}

  paths:
    scan:
      - "${PROJECT_SRC_DIR:-src/}"
      - "packages/"
```

### Configuration Merging Strategies

The system uses different merging strategies based on the type of configuration data:

#### Arrays
- **Indexed Arrays**: Merge and remove duplicates
- **Path Arrays**: Special handling for relative paths and deduplication
- **Associative Arrays**: Deep merge with override

#### Objects
- **Deep Merge**: Recursively merge object properties
- **Override Protection**: Lower priority values are preserved unless explicitly overridden

#### Scalar Values
- **Complete Override**: Higher priority sources completely replace lower priority values

#### Special Cases
- **Tool Config Files**: When present, completely override unified configuration for that tool
- **Path Resolution**: Relative paths are resolved relative to their source file location

### Configuration Management Commands

#### Configuration Show Command

Display current configuration:

```bash
# Show current configuration as YAML
vendor/bin/qt config:show

# Show configuration as JSON
vendor/bin/qt config:show --format=json

# Show configuration with verbose output (includes sources)
vendor/bin/qt config:show -v
```

#### Configuration Validate Command

Validate configuration files:

```bash
# Validate configuration
vendor/bin/qt config:validate

# Validate with verbose output
vendor/bin/qt config:validate -v
```

#### Configuration Initialize Command

Create initial configuration files:

```bash
# Create initial configuration file
vendor/bin/qt config:init

# Use specific project template
vendor/bin/qt config:init --template=typo3-extension
vendor/bin/qt config:init --template=typo3-site-package
vendor/bin/qt config:init --template=typo3-distribution
vendor/bin/qt config:init --template=default

# Force overwrite existing configuration
vendor/bin/qt config:init --force

# Combine options
vendor/bin/qt config:init --template=typo3-extension --force
```

**Available Templates:**
- `typo3-extension`: Optimized for TYPO3 extensions
- `typo3-site-package`: Optimized for TYPO3 site packages
- `typo3-distribution`: Optimized for TYPO3 distributions
- `default`: General purpose configuration

## Runtime Configuration

### Working Directory Behavior

The tool adapts its behavior based on where it's executed:

```bash
# From project root - Uses current directory
/path/to/project$ vendor/bin/qt --version

# From subdirectory - Searches upward automatically
/path/to/project/packages/extension$ ../../vendor/bin/qt --version

# With explicit project root - Uses specified directory
/anywhere$ QT_PROJECT_ROOT=/path/to/project vendor/bin/qt --version
```

### Path Resolution

The tool resolves paths in this order:

1. **QT_PROJECT_ROOT environment variable** (if set)
2. **Automatic detection** from current working directory
3. **Error** if no valid TYPO3 project found

## Integration Examples

### Development Environment

Set up your development environment with persistent configuration:

```bash
# ~/.bashrc or ~/.zshrc
export QT_PROJECT_ROOT=/var/www/myproject
export QT_DEBUG=false

# Project-specific .env file
echo "QT_PROJECT_ROOT=$(pwd)" >> .env
echo "QT_DEBUG=false" >> .env
source .env
```

### CI/CD Integration

Configure for continuous integration:

```yaml
# GitHub Actions example
env:
  QT_PROJECT_ROOT: ${{ github.workspace }}
  QT_DEBUG: false

steps:
  - name: Run Quality Tools
    run: vendor/bin/qt --version --quiet
```

```yaml
# GitLab CI example
variables:
  QT_PROJECT_ROOT: $CI_PROJECT_DIR
  QT_DEBUG: "false"

script:
  - vendor/bin/qt --version --quiet
```

### Docker Integration

Use environment variables in Docker containers:

```dockerfile
# Dockerfile
ENV QT_PROJECT_ROOT=/var/www/html
ENV QT_DEBUG=false

# Or with docker run
docker run \
  -e QT_PROJECT_ROOT=/app \
  -e QT_DEBUG=true \
  myimage vendor/bin/qt --version
```

```yaml
# docker-compose.yml
services:
  web:
    environment:
      - QT_PROJECT_ROOT=/var/www/html
      - QT_DEBUG=false
```

## Configuration Validation

### Environment Variable Validation

The tool validates environment variables at runtime:

```bash
# Valid directory path
export QT_PROJECT_ROOT=/existing/directory
vendor/bin/qt --version  # Works - uses the specified directory as project root

# Non-existent path
export QT_PROJECT_ROOT=/nonexistent/path
vendor/bin/qt --version  # Falls back to automatic TYPO3 project detection

# File path instead of directory
export QT_PROJECT_ROOT=/path/to/file.txt
vendor/bin/qt --version  # Falls back to automatic TYPO3 project detection
```

**Note:** When QT_PROJECT_ROOT is set but invalid (non-existent or not a directory), the tool falls back to automatic TYPO3 project detection from the current working directory.

### Debug Configuration Validation

Test your configuration with debug mode:

```bash
QT_DEBUG=true vendor/bin/qt --version
```

Expected debug output when QT_PROJECT_ROOT is set:
```
Project root detection started
Environment variable QT_PROJECT_ROOT: /path/to/directory
Using project root from QT_PROJECT_ROOT: /path/to/directory
CPSIT Quality Tools 1.0.0-dev
```

Expected debug output with automatic detection:
```
Project root detection started
Searching for TYPO3 project from: /current/working/directory
Found composer.json: /path/to/project/composer.json
TYPO3 dependencies found: typo3/cms-core
Project root confirmed: /path/to/project
CPSIT Quality Tools 1.0.0-dev
```

## Configuration Troubleshooting

### Common Issues

1. **Configuration Not Found**: Check file paths and permissions
2. **Unexpected Values**: Review precedence hierarchy and debug configuration
3. **Tool Not Working**: Verify tool-specific configuration syntax
4. **Environment Variables**: Check variable names and default values

### Debug Commands

```bash
# Show configuration with verbose output (includes sources)
vendor/bin/qt config:show -v

# Validate configuration with verbose output
vendor/bin/qt config:validate -v

# Show configuration as JSON for debugging
vendor/bin/qt config:show --format=json
```

### Getting Help

1. Use `--help` flag with any command for detailed usage information
2. Use `-v` or `--verbose` flag to see detailed configuration loading information
3. Check file permissions and syntax if configuration isn't loading
4. Refer to tool-specific documentation for advanced configuration options

## Best Practices

### Project Setup

1. **Global Defaults**: Set common preferences in `~/.quality-tools.yaml`
2. **Project Customization**: Override only what you need in project root configuration
3. **Environment Specific**: Use config directory for deployment-specific settings
4. **Tool Complexity**: Use tool-specific files only when you need complex configuration

### Configuration Organization

1. **Start Simple**: Begin with project root configuration, add complexity as needed
2. **Minimize Overrides**: Only override settings that differ from sensible defaults
3. **Document Changes**: Comment why specific overrides were made
4. **Version Control**: Commit configuration files, exclude environment-specific secrets

### Performance Considerations

1. **Avoid Deep Nesting**: Keep package directory structures reasonable
2. **Tool-Specific Files**: Use only when unified YAML isn't sufficient
3. **Environment Variables**: Use sparingly to avoid complexity

### Team Collaboration

1. **Shared standards**: Document required environment setup
2. **Default behavior**: Rely on automatic detection when possible
3. **Flexibility**: Provide override options for different development setups
4. **Testing**: Test configuration on different environments

### Migration Guide

#### From Simple to Hierarchical Configuration

1. **Assess Current Setup**: Identify existing configuration files
2. **Plan Hierarchy**: Decide which settings belong at which level
3. **Create Global Config**: Move common settings to `~/.quality-tools.yaml`
4. **Test Thoroughly**: Verify that tools behave as expected
5. **Clean Up**: Remove redundant configuration files
