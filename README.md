CPSIT Quality Tools
===================

A complete command-line interface for TYPO3 quality assurance tools. This package provides both preconfigured tool access via direct commands and a unified CLI with simple shortcuts for common quality assurance tasks.

## Status: MVP Complete with Known Issues

**Version:** 1.0.0-dev
**Test Coverage:** 96.91% (227 tests, 720 assertions)
**All 10 tool commands fully implemented and tested**

**WARNING: Production Testing Revealed Critical Issues** - See [Known Issues](#known-issues) below

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

### CLI Commands (Recommended)
After installation, use the simple `qt` command shortcuts:

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

### Direct Tool Access (Alternative)
You can also run tools directly with full configuration paths:

```bash
# Rector example
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run

# PHPStan example
app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon
```

## Key Features

- **Automatic TYPO3 Project Detection**: Traverses up to 10 directory levels to find your TYPO3 project root
- **Configuration Path Resolution**: Automatically locates preconfigured tool configurations with custom override support (`--config` option)
- **Flexible Target Specification**: Specify custom paths for analysis (`--path` option)
- **Comprehensive Error Handling**: Proper exit codes and detailed error messages
- **Verbose Debug Output**: `--verbose` flag for troubleshooting
- **Extensive Testing**: 96.91% line coverage with 227 tests and 720 assertions

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

## Known Issues

**First production testing revealed 6 critical issues** that need addressing before stable release:

**[Complete Issues Analysis](docs/plan/review/2025-12-18/README.md)** - Detailed post-mortem and analysis

### Critical Issues (5/6 tools failed)

1. **[PHPStan Memory Exhaustion](docs/plan/issue/001-phpstan-memory-exhaustion.md)** - High Priority 
   - Memory exhaustion on large TYPO3 projects (>435 files)
   - **Recommended Fix:** Dynamic memory limit based on project analysis

2. **[PHP CS Fixer Memory Exhaustion](docs/plan/issue/002-php-cs-fixer-memory-exhaustion.md)** - High Priority
   - Similar memory issues with large codebases  
   - **Recommended Fix:** Automatic memory optimization

3. **[Composer Normalize Missing](docs/plan/issue/005-composer-normalize-missing.md)** - High Priority
   - Missing dependency in target projects
   - **Recommended Fix:** Bundle dependency with fallback

4. **[TypoScript Lint Path Option](docs/plan/issue/004-typoscript-lint-path-option.md)** - Medium Priority
   - Command interface mismatch with --path option
   - **Recommended Fix:** Intelligent path discovery

5. **[Fractor YAML Parser Crash](docs/plan/issue/003-fractor-yaml-parser-crash.md)** - Medium Priority
   - TypeError in YAML parsing crashes execution
   - **Recommended Fix:** Automatic error recovery with validation

6. **[Rector Performance Issues](docs/plan/issue/006-rector-performance-large-projects.md)** - Low Priority
   - Slow performance on large projects (>30 seconds)
   - **Recommended Fix:** Automatic performance optimization

**Root Cause:** Over-reliance on mocked testing without real-world integration validation

### Upcoming Fixes

- **[Feature 004: Dynamic Resource Optimization](docs/plan/feature/004-dynamic-resource-optimization.md)** 
  - Addresses memory and performance issues automatically
  - Zero-configuration optimization based on project size
  - Estimated implementation: 8-16 hours

## Table of Contents

### User Guide
- [User Guide](docs/user-guide/index.md) - Complete guide for installing and using the CLI tool
- [Project Planning](docs/plan/index.md) - Complete planning documentation and known issues

### Tool Configuration
- [Fractor](docs/Fractor.md) - TYPO3 Fractor configuration and usage
- [PHP CS Fixer](docs/PhpCsFixer.md) - PHP coding standards fixer configuration
- [PHPStan](docs/Phpstan.md) - Static analysis tool configuration
- [Rector](docs/Rector.md) - TYPO3 Rector configuration and usage
- [TypoScript Lint](docs/TypoScriptLint.md) - TypoScript linting configuration

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
