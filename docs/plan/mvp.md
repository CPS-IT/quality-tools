# MVP Implementation Plan: CPSIT Quality Tools CLI

## Status: COMPLETED

**Implementation Status:** All phases completed
**Test Coverage:** 96.91% line coverage (227 tests, 720 assertions)
**All 10 tool commands implemented and tested**

## Overview

This document outlines the Minimal Viable Product (MVP) implementation plan for the cpsit/quality-tools package. The MVP focuses on creating a simple command-line interface (CLI) tool called 'qt' (quality tools) that wraps existing tool configurations with user-friendly commands.

**This MVP has been successfully completed and is ready for production use.**

## Current State Analysis

### Existing Infrastructure
- **Configuration Files**: Complete configurations for Rector, Fractor, PHPStan, PHP CS Fixer, TypoScript Lint in `config/` directory
- **Tool Dependencies**: All quality tools already defined in `composer.json`
- **TYPO3 Integration**: Configurations automatically detect TYPO3 project root and target appropriate paths
- **Documentation**: Individual tool documentation exists for each quality tool

### Current Pain Points
- **Long Command Paths**: Commands like `app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run` are verbose and error-prone
- **Tool Discovery**: Users need to know individual tool syntax and options
- **Configuration Management**: No unified interface for tool configurations
- **Developer Experience**: Steep learning curve for teams new to quality tools

## MVP Goals

### Primary Objectives
1. **Simplify Command Usage**: Replace long command paths with simple `qt` commands
2. **Standardize Interface**: Provide consistent command structure across all tools
3. **Maintain Flexibility**: Preserve existing tool functionality and options
4. **Zero Breaking Changes**: Existing configurations and usage patterns remain functional

### Success Criteria
- Commands are 80% shorter than current equivalents
- New team members can run quality checks within 5 minutes
- All existing tool functionality remains accessible
- Installation requires no additional configuration

## Core Architecture Decisions

### Simple CLI Framework
- **Symfony Console**: Industry-standard PHP CLI framework
- **Composer Binary**: Install `qt` command via Composer bin configuration
- **Basic Path Resolution**: Simple composer.json traversal for project root detection

### Command Structure
```bash
qt <category>:<action> [options] [arguments]
```

**Categories**:
- `lint`: Static analysis and checking (no file changes)
- `fix`: Automated fixing and code transformation
- `check`: General quality checks and validation

### Tool Mapping Strategy
| Current Command | New Command | Description |
|----------------|-------------|-------------|
| `vendor/bin/rector -c config/rector.php --dry-run` | `qt lint:rector` | Rector dry-run analysis |
| `vendor/bin/rector -c config/rector.php` | `qt fix:rector` | Apply Rector changes |
| `vendor/bin/fractor process --dry-run -c config/fractor.php` | `qt lint:fractor` | Fractor TypoScript analysis |
| `vendor/bin/fractor process -c config/fractor.php` | `qt fix:fractor` | Apply Fractor changes |
| `vendor/bin/phpstan analyse -c config/phpstan.neon` | `qt lint:phpstan` | PHPStan static analysis |
| `vendor/bin/php-cs-fixer fix --dry-run --config=config/php-cs-fixer.php` | `qt lint:php-cs-fixer` | PHP CS Fixer dry-run |
| `vendor/bin/php-cs-fixer fix --config=config/php-cs-fixer.php` | `qt fix:php-cs-fixer` | Apply PHP CS Fixer changes |
| `vendor/bin/typoscript-lint -c config/typoscript-lint.yml` | `qt lint:typoscript` | TypoScript linting |
| `vendor/bin/composer-normalize --dry-run` | `qt lint:composer` | Composer.json validation |
| `vendor/bin/composer-normalize` | `qt fix:composer` | Normalize composer.json |

## Implementation Phases

### Phase 1: Core Implementation
**Objective**: Build working `qt` commands that transform complex tool paths into simple shortcuts

#### Tasks
1. **Set up Symfony Console Application**
   - Create `src/Console/QualityToolsApplication.php`
   - Configure application name, version, and command registration
   - Basic composer.json traversal for project root detection

2. **Create Base Command Class**
   - `src/Console/Command/BaseCommand.php` - Single base class for shared functionality
   - Simple project root detection (find nearest composer.json)
   - Basic configuration path resolution
   - Common option handling (verbose, quiet, config override)

3. **Implement Tool Commands**
   - `src/Console/Command/RectorLintCommand.php` - `qt lint:rector`
   - `src/Console/Command/RectorFixCommand.php` - `qt fix:rector`
   - `src/Console/Command/FractorLintCommand.php` - `qt lint:fractor`
   - `src/Console/Command/FractorFixCommand.php` - `qt fix:fractor`
   - `src/Console/Command/PhpStanCommand.php` - `qt lint:phpstan`
   - `src/Console/Command/PhpCsFixerLintCommand.php` - `qt lint:php-cs-fixer`
   - `src/Console/Command/PhpCsFixerFixCommand.php` - `qt fix:php-cs-fixer`
   - `src/Console/Command/TypoScriptLintCommand.php` - `qt lint:typoscript`
   - `src/Console/Command/ComposerLintCommand.php` - `qt lint:composer`
   - `src/Console/Command/ComposerFixCommand.php` - `qt fix:composer`

4. **Configure Composer Binary**
   - Update `composer.json` with `bin` configuration for `qt` command
   - Create `bin/qt` executable script
   - Set up autoloading for new classes

#### Deliverables
- All `qt lint:*` and `qt fix:*` commands working
- Simple project root detection
- Basic error handling and output forwarding

#### Technical Requirements
- PHP 8.3+ compatibility
- Symfony Console ^6.0 or ^7.0 dependency
- Symfony Process ^6.0 or ^7.0 dependency
- PSR-4 autoloading configuration

### Phase 2: Polish and Production Readiness
**Objective**: Finalize MVP with testing, documentation, and production features

#### Tasks
1. **Testing and Validation**
   - Unit tests for base command functionality
   - Integration tests with real tool execution
   - Error handling validation

2. **Documentation and Help**
   - Command help text and usage examples
   - Update README.md with qt command examples
   - Basic troubleshooting guide

3. **Production Readiness**
   - Proper exit codes and error handling
   - Tool availability validation
   - Release preparation and versioning

#### Deliverables
- Production-ready MVP release
- Basic documentation
- Test coverage for core functionality

## Technical Architecture

### Directory Structure
```
src/
├── Console/
│   ├── QualityToolsApplication.php
│   └── Command/
│       ├── BaseCommand.php
│       ├── RectorLintCommand.php
│       ├── RectorFixCommand.php
│       ├── FractorLintCommand.php
│       ├── FractorFixCommand.php
│       ├── PhpStanCommand.php
│       ├── PhpCsFixerLintCommand.php
│       ├── PhpCsFixerFixCommand.php
│       ├── TypoScriptLintCommand.php
│       ├── ComposerLintCommand.php
│       └── ComposerFixCommand.php

bin/
└── qt

tests/
└── Unit/
```

### Core Classes Design

#### QualityToolsApplication
- Extends Symfony Console Application
- Registers all available commands
- Provides version and metadata

#### BaseCommand
- Single base class for all commands
- Simple project root detection via composer.json traversal
- Basic configuration path calculation
- Common option handling (verbose, quiet, config override)
- Process execution via Symfony Process component
- Output forwarding from underlying tools

#### Tool Commands
- Each tool has dedicated command classes (e.g., RectorLintCommand)
- Minimal logic: build command string and execute
- Inherit common functionality from BaseCommand
- Tool-specific configuration path resolution

### Dependencies

#### Required Dependencies
```json
{
  "symfony/console": "^6.0|^7.0",
  "symfony/process": "^6.0|^7.0"
}
```

#### Rationale
- **Symfony Console**: Industry standard for PHP CLI applications
- **Symfony Process**: Safe external process execution

### Configuration Management

#### Default Behavior
- Use existing configuration files in `config/` directory
- Auto-detect TYPO3 project root via Composer
- Apply default paths based on project structure

#### Override Support
```bash
qt lint:rector --config=/custom/path/rector.php
qt lint:phpstan --path=./custom/src
```

#### Environment Variables
```bash
QT_PROJECT_ROOT=/path/to/project
QT_CONFIG_DIR=/path/to/configs
```

## Risk Assessment and Mitigation

### Technical Risks

#### Risk: Symfony Console Compatibility
**Impact**: Medium - Breaking changes in Symfony Console could affect CLI
**Probability**: Low - Symfony maintains good backward compatibility
**Mitigation**:
- Support multiple Symfony Console versions (^6.0|^7.0)
- Comprehensive test suite covering different versions
- Regular dependency updates and testing

#### Risk: Tool Command Changes
**Impact**: Medium - Changes in underlying tools could break command execution
**Probability**: Medium - Tools like Rector/Fractor evolve frequently
**Mitigation**:
- Version-lock tool dependencies in composer.json
- Abstract tool execution through ProcessRunner service
- Comprehensive integration testing with locked versions

#### Risk: Project Structure Detection Failures
**Impact**: High - CLI won't work if project structure not detected
**Probability**: Low - Uses standard Composer mechanisms
**Mitigation**:
- Multiple detection strategies (Composer, file system, environment)
- Clear error messages for detection failures
- Manual override options for edge cases

### Implementation Risks

#### Risk: Scope Creep
**Impact**: High - Could delay MVP delivery significantly
**Probability**: Medium - Natural tendency to add "just one more feature"
**Mitigation**:
- Strict adherence to MVP feature list
- Document "nice to have" features for future releases
- Regular review of implementation progress against plan

#### Risk: Backward Compatibility Issues
**Impact**: Medium - Existing users could be affected
**Probability**: Low - MVP preserves existing functionality
**Mitigation**:
- No changes to existing configuration files
- Existing command paths continue to work
- Comprehensive compatibility testing

## Success Metrics

### Quantitative Metrics
- **Command Length Reduction**: Achieve 80% reduction in command character count
- **Setup Time**: New users productive within 5 minutes
- **Tool Coverage**: Support 100% of existing tool functionality
- **Performance**: Commands execute within 10% performance overhead

### Qualitative Metrics
- **Developer Experience**: Simplified onboarding and daily usage
- **Consistency**: Uniform command structure and output formatting
- **Documentation**: Clear, actionable documentation with examples
- **Maintainability**: Clean, testable code architecture

## Future Considerations

### Post-MVP Enhancements
1. **Interactive Mode**: Guided quality check workflows
2. **Configuration Generator**: Create custom configurations interactively
3. **CI Integration**: Specialized commands for continuous integration
4. **Reporting**: HTML/JSON output formats for tooling integration
5. **Watch Mode**: Automatic quality checks on file changes

### Architectural Evolution
1. **Plugin System**: Allow third-party tool integrations
2. **Configuration Profiles**: Environment-specific configurations
3. **Remote Configurations**: Shared team configurations
4. **Quality Metrics**: Historical tracking and reporting

This MVP provides a solid foundation for the cpsit/quality-tools package while maintaining simplicity and focusing on immediate developer productivity gains. The phased approach ensures steady progress and early feedback opportunities while building toward a comprehensive quality tools ecosystem.
