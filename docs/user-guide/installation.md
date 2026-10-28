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

This method ensures the tool is available only in development environments and automatically handles all dependencies. Pin to the major version matching your TYPO3 version (e.g. `cpsit/quality-tools:^13.0` for TYPO3 v13) - see [Versioning and TYPO3 Compatibility](versioning.md).

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

Since the tools run from the global installation (see below), they do not need to be dependencies of the project.

### Method 3: Isolated Installation per Project

Use this when the tools' dependencies conflict with the project's, e.g. `ssch/typo3-rector` or `helmich/typo3-typoscript-lint` requiring an older Symfony major than the project uses. qt gets its own directory with its own lock file, so the tool versions are pinned per project without touching the project's dependency tree.

`.build/quality-tools/composer.json`:

```json
{
    "require": {
        "cpsit/quality-tools": "^14.0",
        "phpstan/extension-installer": "^1.4",
        "saschaegerer/phpstan-typo3": "^3.0"
    },
    "replace": {
        "typo3/cms-core": "*",
        "typo3/cms-extbase": "*"
    },
    "config": {
        "allow-plugins": {
            "a9f/fractor-extension-installer": true,
            "phpstan/extension-installer": true
        }
    }
}
```

```bash
composer install -d .build/quality-tools
.build/quality-tools/vendor/bin/qt lint:phpstan
```

* Commit `.build/quality-tools/composer.lock` so local runs and CI use the same tool versions.
* PHPStan extensions (e.g. `saschaegerer/phpstan-typo3`) belong into this installation, next to PHPStan.
* The `replace` section keeps a second copy of TYPO3 out; the project's TYPO3 is loaded through the project's autoloader.

### Where the Tools Come From

qt always runs Rector, PHPStan, Fractor, PHP CS Fixer and TypoScript Lint from the installation it runs from itself, never from the project's `vendor/bin`:

| Installation Method | Tools are taken from |
|-------------------|----------------------|
| Composer require (Method 1) | the project's `vendor/bin` (qt's installation is the project's) |
| Global (Method 2) | `~/.composer/vendor/bin` |
| Isolated (Method 3) | `.build/quality-tools/vendor/bin` |

When the tools run from a separate installation (Method 2 or 3), qt passes the project's Composer autoloader to Rector (`--autoload-file`); PHPStan finds it on its own. Fractor, PHP CS Fixer and TypoScript Lint do not need the project's classes. `composer normalize` is a Composer plugin and keeps running through the project's Composer.

To take the tools from a different directory, set `QT_TOOL_BIN_PATH`:

```bash
QT_TOOL_BIN_PATH=/opt/tools/vendor/bin vendor/bin/qt lint:phpstan
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
| Isolated | `.build/quality-tools/vendor/bin/qt` | `.build/quality-tools/vendor/bin/qt [command]` |

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
2. Check [Versioning and TYPO3 Compatibility](versioning.md) to confirm you installed the version matching your TYPO3 project
3. Learn about [Project Detection](project-detection.md) to understand how the tool finds your TYPO3 project
4. Configure [Environment Variables](configuration.md) if needed
5. Review [Troubleshooting](troubleshooting.md) for common installation issues

## Uninstallation

To remove CPSIT Quality Tools:

```bash
# Local installation
composer remove --dev cpsit/quality-tools

# Global installation
composer global remove cpsit/quality-tools
```
