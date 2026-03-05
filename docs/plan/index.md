# Project Planning Documentation

This directory contains the complete planning and implementation documentation for the CPSIT Quality Tools CLI project.

## Table of Contents

### Project Overview
- **[Project Goals](goal.md)** - High-level objectives and success criteria

### Implementation Iterations
- **[MVP Implementation Plan](001-mvp.md)** - Complete minimal viable product roadmap (COMPLETED)
- **[Configuration System](002-configuration.md)** - Unified YAML configuration and flexible project detection (20–28 hours)
- **[Report Generation](003-reporting.md)** - Standardized reporting with JSON, templates, and CI/CD integration (11–15 hours)

### Core Implementation Features

The following features represent the complete MVP implementation:

- **[001 - Console Application](feature/done/001-console-application.md)** *(2–3 hours)*
  Main Symfony Console application with basic project root detection

- **[002 - Base Command](feature/done/002-base-command.md)** *(2–3 hours)*
  Single base command class with shared functionality for all tools

- **[003 - Tool Commands](feature/done/003-tool-commands.md)** *(4–6 hours)*
  Individual command implementations for all quality tools

- **[004 - Dynamic Resource Optimization](feature/done/004-dynamic-resource-optimization.md)** *(6–10 hours)*
  Automatic project analysis and resource optimization for all tools

**Total Implementation:** 14–22 hours

## Architecture Overview

The project follows these simplified principles:

- **Single Base Class:** One base command class with shared functionality
- **Symfony Console Framework:** Industry-standard CLI foundation
- **Zero Configuration:** Leverages existing tool configurations
- **Minimal Implementation:** Simple command forwarding without over-engineering

## Command Structure

The 'qt' tool transforms verbose commands into simple, user-friendly alternatives:

```bash
# Before
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run

# After
qt lint:rector
```

## Getting Started

1. Read the [MVP Implementation Plan](001-mvp.md) for complete project overview
2. Review individual feature documents in the `feature/` directory
3. Follow the implementation order based on feature dependencies
4. Refer to project goals for success criteria and validation

## Implementation Status

- **Planning Phase:** [COMPLETE] Feature breakdown and architecture design
- **Core Implementation Phase:** [COMPLETE] Full CLI tool with optimization (283 passing tests, 97.9% coverage)
- **Production Testing Phase:** [COMPLETE] Critical issues identified and resolved
- **Release Phase:** [READY] MVP with dynamic optimization ready for production use

## Known Issues

**Code Quality Review:** [2026-01-07 Review](review/2026-01-07/reviewCodeQuality.md)

The following issues have been identified and prioritized for improvement:

**Critical Priority:**
- [x] [007 - PathScanner Exclusion Logic Refactoring](issue/done/007-pathscanner-exclusion-logic-refactoring.md)
- [x] [008 - Resource Cleanup Temporary Files](issue/done/008-resource-cleanup-temporary-files.md)
- [x] [009 - Security Hardening Environment Variables](issue/done/009-security-hardening-environment-variables.md)

**High Priority:**
- [x] [010 - Command Execution Template Pattern](issue/done/010-command-execution-template-pattern.md)
- [x] [011 - Performance Optimization Cache Key Generation](issue/done/011-cache-key-generation-optimization.md)
- [x] [012 - Enhanced Error Handling Structured Responses](issue/done/012-enhanced-error-handling-structured-responses.md)
- [x] [018 - BaseCommand executeProcess Method Refactoring](issue/done/018-basecommand-executeprocess-method-refactoring.md)

**Medium Priority:**
- [x] [013 - Dependency Injection Container Architecture](issue/done/013-dependency-injection-container-architecture.md)
- [x] [014 - Filesystem Abstraction Symfony Filesystem](issue/done/014-filesystem-abstraction-symfony-filesystem.md)
- [x] [015 - Test Mocking Improvements Isolation](issue/done/015-test-mocking-improvements-isolation.md)
- [x] [016 - Configuration Schema Validation](issue/done/016-configuration-schema-validation.md)
- [x] [017 - Property Based Testing Path Resolution](issue/done/017-property-based-testing-path-resolution.md)

**Production Testing Results:** [2025-12-18 Review](review/2025-12-18/README.md)

## Current Active Issues

**High Priority:**
- [ ] [019 - Configuration Class Hierarchy Simplification](issue/019-configuration-class-hierarchy-simplification.md)
- [ ] [020 - DI Configuration Inconsistency](issue/020-di-configuration-inconsistency.md)
- [ ] [021 - Missing Integration Test Coverage](issue/021-missing-integration-test-coverage.md)

## Active Development Features

The MVP is complete. The following features are organized into implementation iterations:

### Iteration 2: Configuration System Features
- [x] **[010 - Unified YAML Configuration System](feature/done/010-unified-yaml-configuration-system.md)** *(6–8 hours)*
  Developer-focused YAML configuration with comments and human-readable format

- [x] **[013 - Additional Packages Paths Scanning](feature/done/013-additional-packages-paths-scanning.md)** *(4–6 hours)*
  Flexible path configuration extending unified YAML system
- [x] **[014 - Vendor Folder Derivation](feature/done/014-vendor-folder-derivation.md)** *(4–6 hours)*
  Automatic vendor path detection for non-standard project structures
- [ ] **[015 - Configuration Overwrites](feature/015-configuration-overwrites.md)** *(6–8 hours)*
  Hierarchical configuration override system for project customization
- [ ] **[026 - Fail on Warnings Configuration](feature/026-fail-on-warnings-configuration.md)** *(4–6 hours)*
  Configurable exit code behavior for linting tools to ensure CI/CD reliability
- [ ] **[031 - Enhanced Schema Validation](feature/031-enhanced-schema-validation.md)** *(4–6 hours)*
  Advanced schema validation patterns for configuration security and tool-specific validation
- [ ] **[032 - EditorConfig CLI Integration](feature/032-editorconfig-integration.md)** *(4–6 hours)*
  EditorConfig validation and fixing for file formatting consistency
- [ ] **[033 - Comprehensive Security Test Suite](feature/033-comprehensive-security-test-suite.md)** *(10–14 hours)*
  Dedicated security test coverage for path validation and attack prevention

### Iteration 3: Report Generation Features
- **[005 - Report Format Research and Standards](feature/005-report-format-research-and-standards.md)** *(3–4 hours)*
  Research and define standardized report formats and unified schema

- **[006 - Unified Report Generation Foundation](feature/006-implement-basics-for-report-generation.md)** *(6–8 hours)*
  Unified infrastructure for all report formats with template engine support

- **[007 - JSON Report Generation](feature/007-json-report-generation.md)** *(2–3 hours)*
  JSON format writer building on unified foundation

### Deferred Features
- **[012 - Human-Readable Reports](feature/012-human-readable-reports.md)** *(6–8 hours)*
  HTML, Markdown, and text format writers building on unified foundation

**Total Active Features:** 9 specifications (45–63 hours)

## Deferred Features

The following features have been deferred to future iterations:

### Machine-Readable Reports (Extended)
- **[008 - XML and JUnit Report Generation](feature/deferred/008-xml-and-junit-report-generation.md)** *(3–4 hours)*
  XML, JUnit, and SARIF format writers building on unified foundation
- **[009 - CI/CD Platform Integration](feature/deferred/009-ci-cd-platform-integration.md)** *(3–4 hours)*
  Templates and quality gates for GitHub Actions, GitLab CI, Azure DevOps, Jenkins

### Configuration Extensions
- **[011 - JSON Configuration Support](feature/deferred/011-json-configuration-support.md)** *(2–3 hours)*
  Machine-friendly JSON format for automation and API integration

### Command Interface Enhancements
- **[016 - Unified Arguments Options](feature/deferred/016-unified-arguments-options.md)** *(6–10 hours)*
  Standardized command-line interface across all tools
- **[017 - Single Package Scanning](feature/deferred/017-single-package-scanning.md)** *(8–12 hours)*
  Individual package analysis for CI/CD and focused workflows
- **[018 - Global Installation](feature/deferred/018-global-installation.md)** *(6–8 hours)*
  Composer global installation support for cross-project usage
- **[019 - Unified Lint Command](feature/deferred/019-qt-lint-command.md)** *(6–8 hours)*
  Unified linting command that runs all analysis tools
- **[020 - Unified Fix Command](feature/deferred/020-qt-fix-command.md)** *(6–8 hours)*
  Unified fixing command that runs all modification tools
- **[021 - EditorConfig CLI Integration](feature/deferred/021-editorconfig-cli-integration.md)** *(4–6 hours)*
  EditorConfig validation for file formatting consistency
- **[022 - EditorConfig Fix Command](feature/deferred/022-editorconfig-fix-command.md)** *(4–6 hours)*
  EditorConfig automatic fixing capabilities

### Advanced Analysis
- **[023 - Code Quality Metrics](feature/deferred/023-code-quality-metrics.md)** *(8–12 hours)*
  Comprehensive quality metrics and scoring system

### Distribution Methods
- **[024 - PHAR Installation](feature/deferred/024-phar-installation.md)** *(4–6 hours)*
  PHAR file distribution method for easy deployment

### Architectural Features
- **[025 - Tool Abstraction](feature/deferred/025-tool-abstraction.md)** *(10–14 hours)*
  Comprehensive tool abstraction layer for easy extension

**Total Deferred Features:** 13 specifications (81–110 hours)

## Feature Overview Table

| Number | Feature                                 | Status   | File                                                                                                   |
|-------:|-----------------------------------------|----------|--------------------------------------------------------------------------------------------------------|
|    001 | Console Application                     | Done     | [001-console-application.md](feature/done/001-console-application.md)                                  |
|    002 | Base Command                            | Done     | [002-base-command.md](feature/done/002-base-command.md)                                                |
|    003 | Tool Commands                           | Done     | [003-tool-commands.md](feature/done/003-tool-commands.md)                                              |
|    004 | Dynamic Resource Optimization           | Done     | [004-dynamic-resource-optimization.md](feature/done/004-dynamic-resource-optimization.md)              |
|    005 | Report Format Research and Standards    | Open     | [005-report-format-research-and-standards.md](feature/005-report-format-research-and-standards.md)     |
|    006 | Unified Report Generation Foundation    | Open     | [006-implement-basics-for-report-generation.md](feature/006-implement-basics-for-report-generation.md) |
|    007 | JSON Report Generation                  | Open     | [007-json-report-generation.md](feature/007-json-report-generation.md)                                 |
|    008 | XML and JUnit Report Generation         | Deferred | [008-xml-and-junit-report-generation.md](feature/deferred/008-xml-and-junit-report-generation.md)      |
|    009 | CI/CD Platform Integration              | Deferred | [009-ci-cd-platform-integration.md](feature/deferred/009-ci-cd-platform-integration.md)                |
|    010 | Unified YAML Configuration System       | Done     | [010-unified-yaml-configuration-system.md](feature/done/010-unified-yaml-configuration-system.md)      |
|    011 | JSON Configuration Support              | Deferred | [011-json-configuration-support.md](feature/deferred/011-json-configuration-support.md)                |
|    012 | Human-Readable Reports                  | Open     | [012-human-readable-reports.md](feature/012-human-readable-reports.md)                                 |
|    013 | Additional Packages Paths Scanning      | Done     | [013-additional-packages-paths-scanning.md](feature/done/013-additional-packages-paths-scanning.md)    |
|    014 | Vendor Folder Derivation                | Done     | [014-vendor-folder-derivation.md](feature/done/014-vendor-folder-derivation.md)                        |
|    015 | Configuration Overwrites                | Open     | [015-configuration-overwrites.md](feature/015-configuration-overwrites.md)                             |
|    016 | Unified Arguments Options               | Deferred | [016-unified-arguments-options.md](feature/deferred/016-unified-arguments-options.md)                  |
|    017 | Single Package Scanning                 | Deferred | [017-single-package-scanning.md](feature/deferred/017-single-package-scanning.md)                      |
|    018 | Global Installation                     | Deferred | [018-global-installation.md](feature/deferred/018-global-installation.md)                              |
|    019 | Unified Lint Command                    | Deferred | [019-qt-lint-command.md](feature/deferred/019-qt-lint-command.md)                                      |
|    020 | Unified Fix Command                     | Deferred | [020-qt-fix-command.md](feature/deferred/020-qt-fix-command.md)                                        |
|    021 | EditorConfig CLI Integration (Deferred) | Deferred | [021-editorconfig-cli-integration.md](feature/deferred/021-editorconfig-cli-integration.md)            |
|    022 | EditorConfig Fix Command                | Deferred | [022-editorconfig-fix-command.md](feature/deferred/022-editorconfig-fix-command.md)                    |
|    023 | Code Quality Metrics                    | Deferred | [023-code-quality-metrics.md](feature/deferred/023-code-quality-metrics.md)                            |
|    024 | PHAR Installation                       | Deferred | [024-phar-installation.md](feature/deferred/024-phar-installation.md)                                  |
|    025 | Tool Abstraction                        | Deferred | [025-tool-abstraction.md](feature/deferred/025-tool-abstraction.md)                                    |
|    026 | Fail on Warnings Configuration          | Open     | [026-fail-on-warnings-configuration.md](feature/026-fail-on-warnings-configuration.md)                 |
|    031 | Enhanced Schema Validation              | Open     | [031-enhanced-schema-validation.md](feature/031-enhanced-schema-validation.md)                         |
|    032 | EditorConfig CLI Integration            | Open     | [032-editorconfig-integration.md](feature/032-editorconfig-integration.md)                             |
|    033 | Comprehensive Security Test Suite       | Open     | [033-comprehensive-security-test-suite.md](feature/033-comprehensive-security-test-suite.md)           |

**Note:** Feature numbers are preserved for existing files. Newer features were renumbered (026, 031-033) to avoid conflicts with older deferred features.

## Issue Overview Table

| Number | Issue                                            | Status | File                                                                                                                          |
|-------:|--------------------------------------------------|--------|-------------------------------------------------------------------------------------------------------------------------------|
|    001 | PHPStan Memory Exhaustion                        | Done   | [001-phpstan-memory-exhaustion.md](issue/done/001-phpstan-memory-exhaustion.md)                                               |
|    002 | PHP CS Fixer Memory Exhaustion                   | Done   | [002-php-cs-fixer-memory-exhaustion.md](issue/done/002-php-cs-fixer-memory-exhaustion.md)                                     |
|    003 | Fractor YAML Parser Crash                        | Done   | [003-fractor-yaml-parser-crash.md](issue/done/003-fractor-yaml-parser-crash.md)                                               |
|    004 | TypoScript Lint Path Option                      | Done   | [004-typoscript-lint-path-option.md](issue/done/004-typoscript-lint-path-option.md)                                           |
|    005 | Composer Normalize Missing                       | Done   | [005-composer-normalize-missing.md](issue/done/005-composer-normalize-missing.md)                                             |
|    006 | Rector Performance Large Projects                | Done   | [006-rector-performance-large-projects.md](issue/done/006-rector-performance-large-projects.md)                               |
|    007 | PathScanner Exclusion Logic Refactoring          | Done   | [007-pathscanner-exclusion-logic-refactoring.md](issue/done/007-pathscanner-exclusion-logic-refactoring.md)                   |
|    008 | Resource Cleanup Temporary Files                 | Done   | [008-resource-cleanup-temporary-files.md](issue/done/008-resource-cleanup-temporary-files.md)                                 |
|    009 | Security Hardening Environment Variables         | Done   | [009-security-hardening-environment-variables.md](issue/done/009-security-hardening-environment-variables.md)                 |
|    010 | Command Execution Template Pattern               | Done   | [010-command-execution-template-pattern.md](issue/done/010-command-execution-template-pattern.md)                             |
|    011 | Cache Key Generation Optimization                | Done   | [011-cache-key-generation-optimization.md](issue/done/011-cache-key-generation-optimization.md)                               |
|    012 | Enhanced Error Handling Structured Responses     | Done   | [012-enhanced-error-handling-structured-responses.md](issue/done/012-enhanced-error-handling-structured-responses.md)         |
|    013 | Dependency Injection Container Architecture      | Done   | [013-dependency-injection-container-architecture.md](issue/done/013-dependency-injection-container-architecture.md)           |
|    014 | Filesystem Abstraction Symfony Filesystem        | Done   | [014-filesystem-abstraction-symfony-filesystem.md](issue/done/014-filesystem-abstraction-symfony-filesystem.md)               |
|    015 | Test Mocking Improvements Isolation              | Done   | [015-test-mocking-improvements-isolation.md](issue/done/015-test-mocking-improvements-isolation.md)                           |
|    016 | Configuration Schema Validation                  | Done   | [016-configuration-schema-validation.md](issue/done/016-configuration-schema-validation.md)                                   |
|    017 | Property Based Testing Path Resolution           | Done   | [017-property-based-testing-path-resolution.md](issue/done/017-property-based-testing-path-resolution.md)                     |
|    018 | BaseCommand ExecuteProcess Method Refactoring    | Done   | [018-basecommand-executeprocess-method-refactoring.md](issue/done/018-basecommand-executeprocess-method-refactoring.md)       |
|    019 | Configuration Class Hierarchy Simplification     | WIP    | [019-configuration-class-hierarchy-simplification.md](issue/019-configuration-class-hierarchy-simplification.md)              |
|    020 | DI Configuration Inconsistency                   | Open   | [020-di-configuration-inconsistency.md](issue/020-di-configuration-inconsistency.md)                                          |
|    021 | Missing Integration Test Coverage                | Open   | [021-missing-integration-test-coverage.md](issue/021-missing-integration-test-coverage.md)                                    |
|    022 | Configuration File Replacement Schema Validation | Done   | [022-configuration-file-replacement-schema-validation.md](issue/done/022-configuration-file-replacement-schema-validation.md) |
|    023 | BaseCommand Service Locator to Constructor Injection | Open   | [023-basecommand-service-locator-to-constructor-injection.md](issue/023-basecommand-service-locator-to-constructor-injection.md) |
