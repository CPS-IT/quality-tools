CPSIT Quality Tools
===================

A complete command-line interface for TYPO3 quality assurance tools. This package provides both preconfigured tool access via direct commands and a unified CLI with simple shortcuts for common quality assurance tasks.

## Status: MVP Complete with Dynamic Optimization

**Version:** 0.1.0
**Test Coverage:** 97.9% (283 tests, 810 assertions)
**All 10 tool commands fully implemented and tested**
**Dynamic Resource Optimization:** - Automatic memory and performance optimization for all tools

## Installation

```shell
composer require --dev cpsit/quality-tools
```

## Included Quality Tools

| Tool                                              | Description                                            |
|---------------------------------------------------|--------------------------------------------------------|
| [a9f/typo3-fractor][typo3-fractor]                | TYPO3-specific code modernization and refactoring tool |
| [armin/editorconfig-cli][editorconfig-cli]        | Command line tool for EditorConfig validation          |
| [ergebnis/composer-normalize][composer-normalize] | Composer plugin to normalize composer.json files       |
| [helmich/typo3-typoscript-lint][typoscript-lint]  | Linter for TYPO3 TypoScript files                      |
| [phpstan/phpstan][phpstan]                        | Static analysis tool for PHP                           |
| [phpunit/php-code-coverage][php-code-coverage]    | Code coverage information for PHP                      |
| [ssch/typo3-rector][typo3-rector]                 | TYPO3-specific automated code upgrades and refactoring |
| [typo3/coding-standards][coding-standards]        | TYPO3 coding standards and code style tools            |

## Quick Start

### Unified YAML Configuration (Recommended)
Create a centralized configuration file for all quality tools:

```bash
# Initialize configuration with templates
vendor/bin/qt config:init --template=typo3-site-package

# Validate your configuration
vendor/bin/qt config:validate

# View resolved configuration
vendor/bin/qt config:show
```

Example `.quality-tools.yaml`:
```yaml
quality-tools:
  project:
    name: "my-typo3-project"
    php_version: "8.3"
    typo3_version: "13.4"

  # Path configuration for flexible scanning
  paths:
    scan:
      - "packages/"
      - "config/system/"
    additional:
      - "src/**/*.php"                    # Custom source directory
      - "vendor/cpsit/*/Classes"          # Scan CPSIT vendor packages
      - "vendor/fr/*/Classes"             # Scan other vendor packages
    exclude_patterns:
      - "packages/legacy/*"               # Exclude legacy packages
      - "vendor/*/Tests/"                 # Exclude vendor tests

  tools:
    rector:
      enabled: true
      level: "typo3-13"
    phpstan:
      enabled: true
      level: 6
      memory_limit: "1G"
```

### CLI Commands
After configuration, use the simple `qt` command shortcuts with automatic optimization:

```bash
# Lint commands (analysis only)
vendor/bin/qt lint:rector          # Rector dry-run analysis
vendor/bin/qt lint:phpstan         # PHPStan static analysis
vendor/bin/qt lint:php-cs-fixer    # PHP CS Fixer analysis
vendor/bin/qt lint:fractor         # Fractor TypoScript analysis
vendor/bin/qt lint:typoscript      # TypoScript Lint validation
vendor/bin/qt lint:composer        # Composer.json validation

# Fix commands (apply changes)
vendor/bin/qt fix:rector           # Apply Rector fixes
vendor/bin/qt fix:php-cs-fixer     # Apply PHP CS Fixer fixes
vendor/bin/qt fix:fractor          # Apply Fractor fixes
vendor/bin/qt fix:composer         # Normalize composer.json

# All commands support --help for options
vendor/bin/qt lint:phpstan --help
```

### Automatic Optimization in Action
Every command automatically optimizes for your project size:

```bash
$ vendor/bin/qt lint:phpstan
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 552M for medium project
Optimization: Enabling parallel processing for improved performance

[PHPStan output follows...]
```

### Direct Tool Access (Alternative)
You can also run tools directly with full configuration paths:

```bash
# Rector example
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run

# PHPStan example
app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon
```


## All Available Commands

### Lint Commands (Analysis Only)
| Command | Tool | Description |
|---------|------|-------------|
| `qt lint:rector` | Rector | Analyze code for modernization opportunities |
| `qt lint:phpstan` | PHPStan | Static analysis with configurable levels (`--level`, `--memory-limit`) |
| `qt lint:php-cs-fixer` | PHP CS Fixer | Check coding standards compliance |
| `qt lint:fractor` | Fractor | Analyze TypoScript for modernization |
| `qt lint:typoscript` | TypoScript Lint | Validate TypoScript syntax and structure |
| `qt lint:composer` | Composer | Validate composer.json structure |

### Fix Commands (Apply Changes)
| Command | Tool | Description |
|---------|------|-------------|
| `qt fix:rector` | Rector | Apply automated code modernization |
| `qt fix:php-cs-fixer` | PHP CS Fixer | Fix coding standards violations |
| `qt fix:fractor` | Fractor | Apply TypoScript modernization |
| `qt fix:composer` | Composer | Normalize composer.json formatting |

### Configuration Commands
| Command | Description |
|---------|-------------|
| `qt config:init` | Initialize YAML configuration with project templates |
| `qt config:validate` | Validate YAML configuration against schema |
| `qt config:show` | Display resolved configuration from all sources |

## Key Features

### Unified YAML Configuration System
- **Centralized Configuration**: Single `.quality-tools.yaml` file for all tools
- **Configuration Hierarchy**: Package defaults -> global user config -> project config -> CLI overrides
- **Environment Variables**: Support for `${VAR:-default}` syntax with type-safe interpolation
- **JSON Schema Validation**: Built-in validation with helpful error messages
- **Project Templates**: Ready-made configurations for different TYPO3 project types
- **Backward Compatibility**: Existing tool-specific configurations continue to work

### Flexible Path Configuration (Feature 013)
- **Additional Paths**: Configure custom paths beyond standard TYPO3 structure
- **Vendor Namespace Patterns**: Scan vendor packages with patterns like "cpsit/*", "fr/*"
- **Glob Pattern Support**: Use powerful glob patterns for path matching
- **Exclusion Patterns**: Exclude specific paths using flexible patterns
- **Tool-Specific Overrides**: Per-tool path configuration for specialized needs
- **Performance Optimized**: Intelligent caching and path resolution

### Dynamic Resource Optimization (Zero Configuration)
- **Automatic Project Analysis**: Analyzes your project size, complexity, and file types to determine optimal settings
- **Smart Memory Management**: Dynamically calculates memory limits (552M for PHPStan, 460M for PHP CS Fixer, 690M for Rector)
- **Performance Optimization**: 50%+ performance improvement through automatic parallel processing and caching
- **Smart Path Scoping**: Defaults to `/packages` directory for TYPO3 projects (analyzing 1,001 files vs 48K+ files)
- **Zero Configuration**: All optimization happens automatically without user input or configuration files

### Project Integration
- **Automatic TYPO3 Project Detection**: Traverses up to 10 directory levels to find your TYPO3 project root
- **Configuration Path Resolution**: Automatically locates preconfigured tool configurations with custom override support (`--config` option)
- **Flexible Target Specification**: Specify custom paths for analysis (`--path` option)
- **Comprehensive Error Handling**: Proper exit codes and detailed error messages

### Advanced Features
- **Optimization Diagnostics**: View project analysis and optimization decisions (shown by default)
- **Manual Override Options**: Disable optimization with `--no-optimization` for edge cases
- **Extensive Testing**: 97.9% line coverage with 283 tests and 810 assertions
- **Performance Monitoring**: Built-in metrics show optimization effectiveness

### Optimization Examples

**Small Project (< 100 files):**
```
Project Analysis: Found 45 files (12 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 256M for small project
Optimization: Standard processing mode selected
```

**Large Project (1000+ files):**
```
Project Analysis: Found 2,847 files (423 PHP files) in /packages
Optimization: Setting Rector memory limit to 1200M for large project
Optimization: Enabling parallel processing and caching for performance
```

**Override Options for Advanced Users:**
```bash
# Disable automatic optimization (also hides optimization details)
vendor/bin/qt lint:phpstan --no-optimization

# Manual memory limit (overrides automatic calculation)
vendor/bin/qt lint:phpstan --memory-limit=1024M
```


## Table of Contents

### User Guide
- [User Guide](docs/user-guide/index.md) - Complete guide for installing and using the CLI tool
- [Project Planning](docs/plan/index.md) - Complete planning documentation and known issues

### Developer Guide
- [Testing Infrastructure](docs/developer-guide/testing.md) - Testing best practices, virtual filesystem, and test isolation

### Configuration Guide
- [YAML Configuration Guide](docs/configuration/yaml-configuration.md) - Complete guide for unified YAML configuration
- [Configuration Reference](docs/configuration/reference.md) - Complete reference of all configuration options
- [Migration Guide](docs/configuration/migration.md) - Migrating from tool-specific to unified configuration
- [Environment Variables](docs/configuration/environment-variables.md) - Using environment variables in configuration
- [Templates](docs/configuration/templates.md) - Project templates and customization

### Tool Configuration
- [Dynamic Resource Optimization](docs/user-guide/optimization.md) - How automatic optimization works
- [Fractor](docs/user-guide/tool/Fractor.md) - TYPO3 Fractor configuration and usage
- [PHP CS Fixer](docs/user-guide/tool/PhpCsFixer.md) - PHP coding standards fixer configuration
- [PHPStan](docs/user-guide/tool/Phpstan.md) - Static analysis tool configuration
- [Rector](docs/user-guide/tool/Rector.md) - TYPO3 Rector configuration and usage
- [TypoScript Lint](docs/user-guide/tool/TypoScriptLint.md) - TypoScript linting configuration

[typo3-fractor]: https://packagist.org/packages/a9f/typo3-fractor
[editorconfig-cli]: https://packagist.org/packages/armin/editorconfig-cli
[codeception]: https://packagist.org/packages/codeception/codeception
[composer-normalize]: https://packagist.org/packages/ergebnis/composer-normalize
[typoscript-lint]: https://packagist.org/packages/helmich/typo3-typoscript-lint
[phpstan]: https://packagist.org/packages/phpstan/phpstan
[php-code-coverage]: https://packagist.org/packages/phpunit/php-code-coverage
[typo3-rector]: https://packagist.org/packages/ssch/typo3-rector
[coding-standards]: https://packagist.org/packages/typo3/coding-standards
[testing-framework]: https://packagist.org/packages/typo3/testing-framework
