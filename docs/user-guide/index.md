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
* [x] **All 10 tool commands fully implemented:**
  * [x] `qt lint:rector` - Rector dry-run analysis
  * [x] `qt fix:rector` - Apply Rector fixes
  * [x] `qt lint:phpstan` - PHPStan static analysis (with --level and --memory-limit options)
  * [x] `qt lint:php-cs-fixer` - PHP CS Fixer analysis
  * [x] `qt fix:php-cs-fixer` - Apply PHP CS Fixer fixes
  * [x] `qt lint:fractor` - Fractor TypoScript analysis
  * [x] `qt fix:fractor` - Apply Fractor fixes
  * [x] `qt lint:typoscript` - TypoScript Lint validation
  * [x] `qt lint:composer` - Composer.json validation
  * [x] `qt fix:composer` - Composer.json normalization
* [x] **Configuration path resolution** with custom override support (`--config` option)
* [x] **Target path specification** (`--path` option)
* [x] **Process output forwarding** with proper exit codes
* [x] **Extensive test coverage** - 96.91% line coverage (227 tests, 720 assertions)

## Table of Contents

1. [Installation Guide](installation.md) - How to install and set up the tool
2. [Getting Started](getting-started.md) - Basic usage and first steps
3. [Project Detection](project-detection.md) - How the tool finds TYPO3 projects
4. [Configuration](configuration.md) - Environment variables and customization options
5. [Troubleshooting](troubleshooting.md) - Common issues and solutions

## Quick Start

For immediate usage, see the [Getting Started](getting-started.md) guide to begin using the tool right away.

## Requirements

* PHP 8.3 or higher
* TYPO3 13.4 or higher
* Composer for dependency management

## Support

If you encounter issues not covered in the troubleshooting guide, please refer to the project documentation or file an issue on the project repository.
