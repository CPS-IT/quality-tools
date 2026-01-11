# CPSIT Quality Tools - User Guide

Welcome to the comprehensive user guide for CPSIT Quality Tools, a complete command-line interface for TYPO3 quality assurance tools.

## Overview

CPSIT Quality Tools provides a unified command-line interface for running various quality assurance tools on TYPO3 projects. The tool automatically detects TYPO3 project structures and provides easy access to preconfigured analysis tools with simple, memorable shortcuts.

**Current Version:** 1.0.0-dev
**Status:** Completed MVP - All features implemented and tested

## What's Included

**Completed MVP Features:**

* [x] Console application foundation with TYPO3 project detection
* [x] TYPO3 project root detection via composer.json traversal (up to 10 levels)
* [x] Environment variable configuration support
* [x] Comprehensive error handling and debug mode
* [x] **Dynamic Resource Optimization (Feature 004):**
  * [x] Automatic project analysis (file counting, complexity analysis)
  * [x] Dynamic memory limit calculation (552M PHPStan, 460M PHP CS Fixer, 690M Rector)
  * [x] Smart path scoping (defaults to `/packages` directory for TYPO3 projects)
  * [x] Performance optimization (50%+ improvement on large projects)
  * [x] Zero configuration with manual override options (`--no-optimization`)
* [x] **All 10 tool commands fully implemented:**
  * [x] `qt lint:rector` - Rector dry-run analysis (with automatic optimization)
  * [x] `qt fix:rector` - Apply Rector fixes (with automatic optimization)
  * [x] `qt lint:phpstan` - PHPStan static analysis (with automatic memory management)
  * [x] `qt lint:php-cs-fixer` - PHP CS Fixer analysis (with automatic optimization)
  * [x] `qt fix:php-cs-fixer` - Apply PHP CS Fixer fixes (with automatic optimization)
  * [x] `qt lint:fractor` - Fractor TypoScript analysis (with automatic optimization)
  * [x] `qt fix:fractor` - Apply Fractor fixes (with automatic optimization)
  * [x] `qt lint:typoscript` - TypoScript Lint validation
  * [x] `qt lint:composer` - Composer.json validation
  * [x] `qt fix:composer` - Composer.json normalization
* [x] **Configuration path resolution** with custom override support (`--config` option)
* [x] **Target path specification** (`--path` option)
* [x] **Process output forwarding** with proper exit codes
* [x] **Extensive test coverage** - 97.9% line coverage (283 tests, 810 assertions)

## Table of Contents

1. [Installation Guide](installation.md) - How to install and set up the tool
2. [Getting Started](getting-started.md) - Basic usage and first steps
3. [Dynamic Resource Optimization](optimization.md) - How automatic optimization works
4. [Project Detection](project-detection.md) - How the tool finds TYPO3 projects
5. [Configuration](configuration.md) - Configuration system with hierarchical override support
6. [Troubleshooting](troubleshooting.md) - Common issues and solutions

## Configuration System

The hierarchical configuration system provides powerful yet simple configuration management:

7. [Configuration Reference](configuration/reference.md) - Complete reference for all configuration options and hierarchy
8. [YAML Configuration Guide](configuration/yaml-configuration.md) - Complete guide to the unified YAML configuration system
9. [Project Templates](configuration/templates.md) - Pre-configured setups for different TYPO3 project types
10. [Environment Variables](configuration/environment-variables.md) - Using environment variables in configurations
11. [Migration Guide](configuration/migration.md) - Migrating from tool-specific configurations
12. [Configuration Troubleshooting](configuration/troubleshooting.md) - Diagnosing configuration issues

## Quick Start

For immediate usage, see the [Getting Started](getting-started.md) guide to begin using the tool right away. All tools now include automatic optimization for optimal performance without configuration.

## Key Benefits

### Zero Configuration Experience
- **Automatic Resource Management**: Tools automatically optimize memory limits and performance settings
- **Smart Project Analysis**: Analyzes your project to determine optimal processing strategies
- **Performance Improvements**: 50%+ performance improvement on large projects through automatic optimization
- **Memory Issue Resolution**: Eliminates memory exhaustion problems that previously required manual configuration

### Example Output
```
$ vendor/bin/qt lint:phpstan
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 552M for medium project
Optimization: Enabling parallel processing for improved performance

[PHPStan analysis continues...]
```

## Requirements

* PHP 8.3 or higher
* TYPO3 13.4 or higher
* Composer for dependency management

## Support

If you encounter issues not covered in the troubleshooting guide, please refer to the project documentation or file an issue on the project repository.
