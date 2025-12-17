# Getting Started

This guide will help you start using CPSIT Quality Tools with your TYPO3 project.

## Prerequisites

Before you begin, ensure you have:

* [Installed CPSIT Quality Tools](installation.md) in your TYPO3 project
* A valid TYPO3 project with `composer.json` containing TYPO3 dependencies
* PHP CLI access to run commands

## Basic Usage

### Running the Tool

Navigate to your TYPO3 project root and run the quality tools command:

```bash
# If installed locally in project
vendor/bin/qt

# If installed globally
qt
```

### Available Commands

CPSIT Quality Tools provides a complete set of quality assurance commands:

#### Help and Information Commands
```bash
# View all available commands and options
vendor/bin/qt help
vendor/bin/qt --help

# Check the installed version
vendor/bin/qt --version

# List all available commands
vendor/bin/qt list
```

#### Lint Commands (Analysis Only)
These commands analyze your code without making changes:

```bash
# Rector - Analyze code for modernization opportunities
vendor/bin/qt lint:rector

# PHPStan - Static analysis with configurable options
vendor/bin/qt lint:phpstan
vendor/bin/qt lint:phpstan --level=8 --memory-limit=1G

# PHP CS Fixer - Check coding standards compliance
vendor/bin/qt lint:php-cs-fixer

# Fractor - Analyze TypoScript for modernization
vendor/bin/qt lint:fractor

# TypoScript Lint - Validate TypoScript syntax
vendor/bin/qt lint:typoscript

# Composer - Validate composer.json structure
vendor/bin/qt lint:composer
```

#### Fix Commands (Apply Changes)
These commands make actual changes to your codebase:

```bash
# Rector - Apply automated code modernization
vendor/bin/qt fix:rector

# PHP CS Fixer - Fix coding standards violations
vendor/bin/qt fix:php-cs-fixer

# Fractor - Apply TypoScript modernization
vendor/bin/qt fix:fractor

# Composer - Normalize composer.json formatting
vendor/bin/qt fix:composer
```

#### Command Options
All commands support these common options:

```bash
# Custom configuration file
vendor/bin/qt lint:rector --config=/path/to/custom/rector.php

# Custom target path
vendor/bin/qt lint:phpstan --path=packages/my-extension

# Verbose output for debugging
vendor/bin/qt lint:rector --verbose

# Get help for specific command
vendor/bin/qt lint:phpstan --help
```

## Project Detection

CPSIT Quality Tools automatically detects your TYPO3 project structure:

### Automatic Detection
The tool searches from your current directory upward through the filesystem to find a TYPO3 project root. It identifies TYPO3 projects by looking for `composer.json` files containing TYPO3 dependencies.

### Supported Project Structures

The tool works with various TYPO3 project structures:

**Standard Composer-based TYPO3:**
```
your-project/
├── composer.json       # Contains typo3/cms-core dependency
├── vendor/
├── config/
└── packages/
```

**TYPO3 with Custom Package Structure:**
```
your-project/
├── composer.json       # Contains typo3/cms dependency
├── vendor/
├── config/
├── packages/
│   ├── your-sitepackage/
│   └── custom-extension/
└── ...
```

## Working Directory

You can run CPSIT Quality Tools from anywhere within your TYPO3 project:

```bash
# From project root
vendor/bin/qt --version

# From subdirectory (e.g., packages/your-extension/)
../../vendor/bin/qt --version

# From config directory
../vendor/bin/qt --version
```

The tool will automatically find the project root regardless of your current working directory.

## Command Structure

CPSIT Quality Tools follows standard Symfony Console application patterns:

```bash
vendor/bin/qt [options] [command] [arguments]
```

### Global Options

These options are available for all commands:

| Option | Description |
|--------|-------------|
| `-h, --help` | Display help information |
| `-V, --version` | Display version information |
| `-v, --verbose` | Increase verbosity of output |
| `-q, --quiet` | Suppress output |

### Examples

```bash
# Get help for the application
vendor/bin/qt --help

# Check version with verbose output
vendor/bin/qt --version --verbose

# Run with quiet mode (minimal output)
vendor/bin/qt --quiet [command]
```

## Current Status: Completed MVP

### All Features Implemented
* [x] Console application with TYPO3 project detection (up to 10 directory levels)
* [x] Help, version, and list commands
* [x] Automatic project root discovery with configuration path resolution
* [x] Environment variable support
* [x] **All 10 tool commands fully implemented and tested:**
  * [x] Rector commands (`lint:rector`, `fix:rector`)
  * [x] PHPStan command with configurable options (`lint:phpstan`)
  * [x] Fractor commands for TypoScript (`lint:fractor`, `fix:fractor`)
  * [x] PHP CS Fixer commands (`lint:php-cs-fixer`, `fix:php-cs-fixer`)
  * [x] TypoScript Lint command (`lint:typoscript`)
  * [x] Composer commands (`lint:composer`, `fix:composer`)
* [x] Configuration override support (`--config` option)
* [x] Target path specification (`--path` option)
* [x] Verbose debugging output (`--verbose` option)
* [x] Comprehensive error handling with proper exit codes
* [x] Extensive test coverage (96.91% line coverage, 227 tests)

## Next Steps

Now that you understand the basics:

1. **Learn about project detection**: Read [Project Detection](project-detection.md) to understand how the tool finds your TYPO3 project
2. **Configure the tool**: Check [Configuration](configuration.md) for environment variables and customization options
3. **Troubleshoot issues**: See [Troubleshooting](troubleshooting.md) for solutions to common problems

## Tips for Effective Usage

### Best Practices

1. **Run from project root**: While the tool can detect projects from subdirectories, running from the root is most reliable
2. **Use version control**: Always commit your changes before running quality tools that modify code
3. **Test incrementally**: Start with lint commands to analyze before applying fixes
4. **Use lint before fix**: Always run lint commands first to see what changes will be made

### Integration with Development Workflow

Consider integrating CPSIT Quality Tools into your development process:

```bash
# Example development workflow
git checkout -b feature/new-functionality
# ... make your changes ...

# Analyze code quality
vendor/bin/qt lint:rector
vendor/bin/qt lint:phpstan
vendor/bin/qt lint:php-cs-fixer

# Apply fixes if needed
vendor/bin/qt fix:php-cs-fixer
vendor/bin/qt fix:rector

# Final validation
vendor/bin/qt lint:phpstan
git add .
git commit -m "Your changes with quality improvements"
```
