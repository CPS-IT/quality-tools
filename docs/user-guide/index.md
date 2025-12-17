# CPSIT Quality Tools - User Guide

Welcome to the comprehensive user guide for CPSIT Quality Tools, a command-line interface for TYPO3 quality assurance tools.

## Overview

CPSIT Quality Tools provides a unified command-line interface for running various quality assurance tools on TYPO3 projects. The tool automatically detects TYPO3 project structures and provides easy access to preconfigured analysis tools.

**Current Version:** 1.0.0-dev

## What's Included

Currently implemented features:

* [x] Console application foundation with project detection
* [x] TYPO3 project root detection via composer.json traversal
* [x] Environment variable configuration support
* [x] Error handling and debug mode
* [ ] Individual tool commands (rector, phpstan, etc.) - Coming soon

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