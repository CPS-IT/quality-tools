# Installation Guide

This guide covers the installation and setup of CPSIT Quality Tools for your TYPO3 project.

## Requirements

Before installing CPSIT Quality Tools, ensure your system meets these requirements:

* **PHP**: 8.3 or higher
* **TYPO3**: 13.4 or higher  
* **Composer**: Latest version recommended
* **Operating System**: Linux, macOS, or Windows with proper PHP CLI support

## Installation Methods

### Method 1: Composer Require (Recommended)

Install CPSIT Quality Tools as a development dependency in your TYPO3 project:

```bash
composer require --dev cpsit/quality-tools
```

This method ensures the tool is available only in development environments and automatically handles all dependencies.

### Method 2: Global Composer Installation

Install the tool globally to use across multiple projects:

```bash
composer global require cpsit/quality-tools
```

Make sure your global Composer bin directory is in your PATH:

```bash
# Add to your shell profile (.bashrc, .zshrc, etc.)
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

## Verification

After installation, verify the tool is working correctly:

```bash
# If installed locally in project
vendor/bin/qt --version

# If installed globally
qt --version
```

Expected output:
```
CPSIT Quality Tools 1.0.0-dev
```

## Installation Locations

The tool will be available at different paths depending on your installation method:

| Installation Method | Binary Location | Usage Command |
|-------------------|-----------------|---------------|
| Local (project) | `vendor/bin/qt` | `vendor/bin/qt [command]` |
| Global | `~/.composer/vendor/bin/qt` | `qt [command]` |

## Post-Installation Setup

### 1. TYPO3 Project Structure

Ensure your TYPO3 project has a valid `composer.json` file with TYPO3 dependencies. The tool detects TYPO3 projects by looking for these packages:

* `typo3/cms-core`
* `typo3/cms`  
* `typo3/minimal`

### 2. Directory Structure

Your TYPO3 project should follow a standard structure:

```
your-project/
├── composer.json          # Contains TYPO3 dependencies
├── vendor/               # Composer dependencies
│   └── bin/
│       └── qt           # Quality Tools binary
├── config/              # TYPO3 configuration
├── packages/            # Custom packages/extensions
└── ...
```

### 3. Permissions

Ensure the binary has execute permissions:

```bash
chmod +x vendor/bin/qt
```

## Dependencies

CPSIT Quality Tools automatically installs these tools and their configurations:

* **Rector** - PHP code modernization and automated refactoring
* **Fractor** - TYPO3 TypoScript modernization  
* **PHPStan** - Static analysis for PHP code quality
* **PHP CS Fixer** - Code style fixing based on TYPO3 standards
* **TypoScript Lint** - Linting for TYPO3 TypoScript files
* **EditorConfig CLI** - EditorConfig validation
* **Codeception** - Testing framework integration
* **Composer Normalize** - Composer.json normalization

## Next Steps

Once installation is complete:

1. Read the [Getting Started](getting-started.md) guide for basic usage
2. Learn about [Project Detection](project-detection.md) to understand how the tool finds your TYPO3 project
3. Configure [Environment Variables](configuration.md) if needed
4. Review [Troubleshooting](troubleshooting.md) for common installation issues

## Uninstallation

To remove CPSIT Quality Tools:

```bash
# Local installation
composer remove --dev cpsit/quality-tools

# Global installation  
composer global remove cpsit/quality-tools
```