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

### Available Commands (Current Version)

The current implementation provides these basic commands:

#### Help Command
View all available commands and options:

```bash
vendor/bin/qt help
# or
vendor/bin/qt --help
```

#### Version Information
Check the installed version:

```bash
vendor/bin/qt --version
```

Expected output:
```
CPSIT Quality Tools 1.0.0-dev
```

#### List Commands
See all available commands:

```bash
vendor/bin/qt list
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

## Current Status and Upcoming Features

### Currently Available
* [x] Console application with TYPO3 project detection
* [x] Help and version commands
* [x] Automatic project root discovery
* [x] Environment variable support

### Coming Soon
* [ ] Rector command for PHP code modernization
* [ ] PHPStan command for static analysis
* [ ] Fractor command for TypoScript modernization
* [ ] PHP CS Fixer command for code style
* [ ] TypoScript Lint command
* [ ] Batch processing commands

## Next Steps

Now that you understand the basics:

1. **Learn about project detection**: Read [Project Detection](project-detection.md) to understand how the tool finds your TYPO3 project
2. **Configure the tool**: Check [Configuration](configuration.md) for environment variables and customization options  
3. **Troubleshoot issues**: See [Troubleshooting](troubleshooting.md) for solutions to common problems

## Tips for Effective Usage

### Best Practices

1. **Run from project root**: While the tool can detect projects from subdirectories, running from the root is most reliable
2. **Use version control**: Always commit your changes before running quality tools that modify code
3. **Test incrementally**: When quality tools become available, test them on small portions of your codebase first

### Integration with Development Workflow

Consider integrating CPSIT Quality Tools into your development process:

```bash
# Example development workflow
git checkout -b feature/new-functionality
# ... make your changes ...
vendor/bin/qt [quality-command]  # When available
git add .
git commit -m "Your changes with quality improvements"
```