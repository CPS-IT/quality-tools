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
- [ ] [020 - DI Configuration Inconsistency](issue/done/020-di-configuration-inconsistency.md)
- [x] [021 - Missing Integration Test Coverage](issue/021-missing-integration-test-coverage.md)
- [ ] [025 - PathResolutionService Tool Paths Structure](issue/025-path-resolution-service-tool-paths-structure.md)

## Active Development Features

The MVP is complete. The following features are organized into implementation iterations:

### Iteration 2: Configuration System Features
- [x] **[010 - Unified YAML Configuration System](feature/done/010-unified-yaml-configuration-system.md)** *(6–8 hours)*
  Developer-focused YAML configuration with comments and human-readable format

- [x] **[013 - Additional Packages Paths Scanning](feature/done/013-additional-packages-paths-scanning.md)** *(4–6 hours)*
  Flexible path configuration extending unified YAML system
- [x] **[014 - Vendor Folder Derivation](feature/done/014-vendor-folder-derivation.md)** *(4–6 hours)*
  Automatic vendor path detection for non-standard project structures
- [x] **[015 - Configuration Overwrites](feature/done/015-configuration-overwrites.md)** *(6–8 hours)*
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
|    015 | Configuration Overwrites                | Done     | [015-configuration-overwrites.md](feature/done/015-configuration-overwrites.md)                        |
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
|    026 | Fail on Warnings Configuration          | Open     | [026-fail-on-warnings-configuration.md](feature/026-fail-on-warnings-configuration.md) (GL#10 / GH#8)  |
|    031 | Enhanced Schema Validation              | Open     | [031-enhanced-schema-validation.md](feature/031-enhanced-schema-validation.md) (GL#11)                 |
|    032 | EditorConfig CLI Integration            | Open     | [032-editorconfig-integration.md](feature/032-editorconfig-integration.md) (GL#12 / GH#9)              |
|    033 | Comprehensive Security Test Suite       | Open     | [033-comprehensive-security-test-suite.md](feature/033-comprehensive-security-test-suite.md) (GL#13)   |
|    034 | XLF / XLIFF File Linting                | Open     | [034-lint-xlf-files.md](feature/034-lint-xlf-files.md) (GL#4 / GH#4)                                   |

**Note:** Feature numbers are preserved for existing files. Newer features were renumbered (026, 031-033) to avoid conflicts with older deferred features.

## Work Items by Epic

Source of truth: the GitLab tracker [DevOps/testing/quality-tools][gl-issues], generated 2026-06-10. Each row links to its GitLab work item; a GitHub link is shown for the issues mirrored on the public [CPS-IT/quality-tools][gh-repo] repository. Estimates come from the GitLab time-tracking field (GitLab counts `1d` as `8h`). The `Spec` column links to a dedicated spec/story document where one exists, otherwise to the [epic breakdown][epics].

### Summary

| Epic                                                         |  Items |   Open | Closed |  Estimate |
|--------------------------------------------------------------|-------:|-------:|-------:|----------:|
| [Epic 1: TYPO3 v14 Compatibility][ms-1]                      |      4 |      4 |      0 |      11 h |
| [Epic 2: Platform Stability and Defect Resolution][ms-2]     |      5 |      4 |      1 |    15.5 h |
| [Epic 3: Machine-Readable Report Generation][ms-3]           |      8 |      8 |      0 |      27 h |
| [Epic 4: Human-Readable Quality Reports][ms-4]               |      2 |      2 |      0 |       9 h |
| [Epic 5: Reliable CI/CD Quality Gates][ms-5]                 |      2 |      2 |      0 |     8.5 h |
| [Epic 6: Enhanced Configuration and Schema Validation][ms-6] |      4 |      4 |      0 |      10 h |
| [Epic 7: EditorConfig Integration][ms-7]                     |      3 |      3 |      0 |      15 h |
| [Epic 8: Unified Quality Commands][ms-8]                     |      2 |      2 |      0 |       7 h |
| [Epic 9: Cross-Project Quality Dashboard][ms-9]              |      5 |      5 |      0 |      86 h |
| Pre-tracker (closed)                                         |      2 |      0 |      2 |        -- |
| **Total**                                                    | **37** | **34** |  **3** | **189 h** |

### [Epic 1: TYPO3 v14 Compatibility][ms-1]

| Ticket       | Title                                           | Spec                    | Status | Estimate | GitHub |
|--------------|-------------------------------------------------|-------------------------|--------|---------:|--------|
| [#14][gl-14] | Story 1.1: Versioned Rector configs (v13/v14)   | [story][spec-story-1-1] | Open   |       3h | --     |
| [#15][gl-15] | Story 1.2: Versioned Fractor configs (v13/v14)  | [epics.md][epics]       | Open   |       4h | --     |
| [#16][gl-16] | Story 1.3: Publish TYPO3 version support matrix | [epics.md][epics]       | Open   |       2h | --     |
| [#17][gl-17] | Story 1.4: LTS/ELTS bugfix branch strategy      | [epics.md][epics]       | Open   |       2h | --     |

### [Epic 2: Platform Stability and Defect Resolution][ms-2]

| Ticket       | Title                                                   | Spec                            | Status | Estimate | GitHub       |
|--------------|---------------------------------------------------------|---------------------------------|--------|---------:|--------------|
| [#18][gl-18] | Story 2.1: Triage all open GitLab issues                | [epics.md][epics]               | Open   |       2h | --           |
| [#9][gl-9]   | Story 2.2: PathResolutionService returns flat path list | [story][spec-story-2-2]         | Closed |       4h | [GH#7][gh-7] |
| [#19][gl-19] | Story 2.3: Resolve DI configuration inconsistency       | [issue 020][spec-issue-020]     | Open   |   2h 30m | --           |
| [#13][gl-13] | Story 2.4: Comprehensive security test suite            | [feature 033][spec-feature-033] | Open   |       3h | --           |
| [#7][gl-7]   | Story 2.5: Overwrite of typoscript-lint.yml failed      | [issue 026][spec-issue-026]     | Open   |       4h | --           |

### [Epic 3: Machine-Readable Report Generation][ms-3]

| Ticket       | Title                                                            | Spec                        | Status | Estimate | GitHub |
|--------------|------------------------------------------------------------------|-----------------------------|--------|---------:|--------|
| [#20][gl-20] | Story 3.1: Extend ToolRunResult with typed findings              | [epics.md][epics]           | Open   |       6h | --     |
| [#21][gl-21] | Story 3.2: Shared contracts package with upgrade-analyser        | [epics.md][epics]           | Open   |       1d | --     |
| [#22][gl-22] | Story 3.4: Dual-output + Finding DTOs (PHPStan)                  | [epics.md][epics]           | Open   |       2h | --     |
| [#23][gl-23] | Story 3.5: Dual-output + Finding DTOs (Rector)                   | [epics.md][epics]           | Open   |       2h | --     |
| [#24][gl-24] | Story 3.6: Dual-output + Finding DTOs (CS Fixer/Fractor/TS Lint) | [epics.md][epics]           | Open   |       4h | --     |
| [#25][gl-25] | Story 3.7: ReportWriter and JSON report output                   | [epics.md][epics]           | Open   |       4h | --     |
| [#26][gl-26] | Story 3.8: Publish JSON report schema documentation              | [epics.md][epics]           | Open   |       1h | --     |
| [#8][gl-8]   | Support more parameters (tool-specific pass-through)             | [issue 028][spec-issue-028] | Open   |       -- | --     |

### [Epic 4: Human-Readable Quality Reports][ms-4]

| Ticket       | Title                                       | Spec              | Status | Estimate | GitHub |
|--------------|---------------------------------------------|-------------------|--------|---------:|--------|
| [#27][gl-27] | Story 4.1: Implement Markdown report output | [epics.md][epics] | Open   |       4h | --     |
| [#28][gl-28] | Story 4.2: Implement HTML report output     | [epics.md][epics] | Open   |       5h | --     |

### [Epic 5: Reliable CI/CD Quality Gates][ms-5]

| Ticket       | Title                                                | Spec                            | Status | Estimate | GitHub       |
|--------------|------------------------------------------------------|---------------------------------|--------|---------:|--------------|
| [#10][gl-10] | Configurable exit code (fail-on-warnings)            | [feature 026][spec-feature-026] | Open   |   3h 30m | [GH#8][gh-8] |
| [#29][gl-29] | Story 5.1: Warning detection in all existing runners | [epics.md][epics]               | Open   |       5h | --           |

### [Epic 6: Enhanced Configuration and Schema Validation][ms-6]

| Ticket       | Title                                                      | Spec                            | Status | Estimate | GitHub       |
|--------------|------------------------------------------------------------|---------------------------------|--------|---------:|--------------|
| [#3][gl-3]   | Lint Composer within the bundle itself                     | [issue 027][spec-issue-027]     | Open   |       2h | [GH#3][gh-3] |
| [#11][gl-11] | Enhanced schema validation for config_file paths           | [feature 031][spec-feature-031] | Open   |       2h | --           |
| [#30][gl-30] | Story 6.1: Enhanced config:validate error messages         | [epics.md][epics]               | Open   |       3h | --           |
| [#31][gl-31] | Story 6.2: Additional scan paths + tool-specific overrides | [epics.md][epics]               | Open   |       3h | --           |

### [Epic 7: EditorConfig Integration][ms-7]

| Ticket       | Title                                                  | Spec                            | Status | Estimate | GitHub       |
|--------------|--------------------------------------------------------|---------------------------------|--------|---------:|--------------|
| [#4][gl-4]   | qt should lint xlf files                               | [feature 034][spec-feature-034] | Open   |       4h | [GH#4][gh-4] |
| [#12][gl-12] | EditorConfig CLI integration (lint/fix:editorconfig)   | [feature 032][spec-feature-032] | Open   |       1d | [GH#9][gh-9] |
| [#32][gl-32] | Story 7.2: Auto-provision .editorconfig on config:init | [epics.md][epics]               | Open   |       3h | --           |

### [Epic 8: Unified Quality Commands][ms-8]

| Ticket       | Title                                                | Spec              | Status | Estimate | GitHub |
|--------------|------------------------------------------------------|-------------------|--------|---------:|--------|
| [#33][gl-33] | Story 8.1: Unified qt lint and qt fix commands       | [epics.md][epics] | Open   |       4h | --     |
| [#34][gl-34] | Story 8.2: Runner enable/disable and execution order | [epics.md][epics] | Open   |       3h | --     |

### [Epic 9: Cross-Project Quality Dashboard][ms-9]

| Ticket       | Title                                                       | Spec              | Status | Estimate | GitHub |
|--------------|-------------------------------------------------------------|-------------------|--------|---------:|--------|
| [#35][gl-35] | Story 9.1: Aggregation service ingestion endpoint           | [epics.md][epics] | Open   |       6h | --     |
| [#36][gl-36] | Story 9.2: Per-project status storage and trend calculation | [epics.md][epics] | Open   |       1d | --     |
| [#37][gl-37] | Story 9.3: Cross-project dashboard (tech lead view)         | [epics.md][epics] | Open   |       3d | --     |
| [#38][gl-38] | Story 9.4: Non-technical summary view                       | [epics.md][epics] | Open   |       3d | --     |
| [#39][gl-39] | Story 9.5: Automated issue creation from report thresholds  | [epics.md][epics] | Open   |       3d | --     |

### Pre-tracker (closed, no epic)

| Ticket     | Title                                                | Spec                            | Status | Estimate | GitHub       |
|------------|------------------------------------------------------|---------------------------------|--------|---------:|--------------|
| [#1][gl-1] | Configuration Overwrites (Feature 015)               | [feature 015][spec-feature-015] | Closed |       -- | [GH#1][gh-1] |
| [#5][gl-5] | Configuration File Replacement Schema Validation Bug | [issue 022][spec-issue-022]     | Closed |       -- | [GH#5][gh-5] |

<!-- Reference link definitions -->

[gl-issues]: https://gitlab.321.works/DevOps/testing/quality-tools/-/issues
[gh-repo]: https://github.com/CPS-IT/quality-tools/issues
[epics]: ../../_bmad-output/planning-artifacts/epics.md

[ms-1]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/1
[ms-2]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/2
[ms-3]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/3
[ms-4]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/4
[ms-5]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/5
[ms-6]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/6
[ms-7]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/7
[ms-8]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/8
[ms-9]: https://gitlab.321.works/DevOps/testing/quality-tools/-/milestones/9

[gl-1]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/1
[gl-3]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/3
[gl-4]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/4
[gl-5]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/5
[gl-7]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/7
[gl-8]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/8
[gl-9]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/9
[gl-10]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/10
[gl-11]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/11
[gl-12]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/12
[gl-13]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/13
[gl-14]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/14
[gl-15]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/15
[gl-16]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/16
[gl-17]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/17
[gl-18]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/18
[gl-19]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/19
[gl-20]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/20
[gl-21]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/21
[gl-22]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/22
[gl-23]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/23
[gl-24]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/24
[gl-25]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/25
[gl-26]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/26
[gl-27]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/27
[gl-28]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/28
[gl-29]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/29
[gl-30]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/30
[gl-31]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/31
[gl-32]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/32
[gl-33]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/33
[gl-34]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/34
[gl-35]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/35
[gl-36]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/36
[gl-37]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/37
[gl-38]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/38
[gl-39]: https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/39

[gh-1]: https://github.com/CPS-IT/quality-tools/issues/1
[gh-3]: https://github.com/CPS-IT/quality-tools/issues/3
[gh-4]: https://github.com/CPS-IT/quality-tools/issues/4
[gh-5]: https://github.com/CPS-IT/quality-tools/issues/5
[gh-7]: https://github.com/CPS-IT/quality-tools/issues/7
[gh-8]: https://github.com/CPS-IT/quality-tools/issues/8
[gh-9]: https://github.com/CPS-IT/quality-tools/issues/9

[spec-story-1-1]: ../../_bmad-output/implementation-artifacts/1-1-add-versioned-rector-configurations-for-typo3-v13-and-v14.md
[spec-story-2-2]: ../../_bmad-output/implementation-artifacts/2-2-fix-pathresolutionservice-nested-structure-bug-issue-025.md
[spec-issue-020]: issue/done/020-di-configuration-inconsistency.md
[spec-issue-022]: issue/done/022-configuration-file-replacement-schema-validation.md
[spec-issue-026]: issue/026-typoscript-lint-yaml-extension-not-recognized.md
[spec-issue-027]: issue/027-lint-composer-bundle-scope.md
[spec-issue-028]: issue/028-pass-through-tool-specific-parameters.md
[spec-feature-015]: feature/done/015-configuration-overwrites.md
[spec-feature-031]: feature/031-enhanced-schema-validation.md
[spec-feature-032]: feature/032-editorconfig-integration.md
[spec-feature-033]: feature/033-comprehensive-security-test-suite.md
[spec-feature-034]: feature/034-lint-xlf-files.md
[spec-feature-026]: feature/026-fail-on-warnings-configuration.md
