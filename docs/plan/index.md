# Project Planning Documentation

This directory contains the complete planning and implementation documentation for the CPSIT Quality Tools CLI project.

## Table of Contents

### Project Overview
- **[MVP Implementation Plan](mvp.md)** - Complete minimal viable product roadmap for the 'qt' CLI tool
- **[Project Goals](goal.md)** - High-level objectives and success criteria

### Core Implementation Features

The following features represent Phase 1 of the simplified MVP implementation:

- **[001 - Console Application](feature/done/001-console-application.md)** *(2-3 hours)*
  Main Symfony Console application with basic project root detection

- **[002 - Base Command](feature/done/002-base-command.md)** *(2-3 hours)*
  Single base command class with shared functionality for all tools

- **[003 - Tool Commands](feature/done/003-tool-commands.md)** *(4-6 hours)*
  Individual command implementations for all quality tools

**Total Core Implementation:** 8-12 hours

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

1. Read the [MVP Implementation Plan](mvp.md) for complete project overview
2. Review individual feature documents in the `feature/` directory
3. Follow the implementation order based on feature dependencies
4. Refer to project goals for success criteria and validation

## Implementation Status

- **Planning Phase:** [COMPLETE] Simplified feature breakdown
- **Core Implementation Phase:** [COMPLETE] Working CLI tool (227 passing tests)
- **Production Testing Phase:** [FAILED] Revealed critical issues requiring fixes
- **Polish Phase:** [IN PROGRESS] Addressing production issues

## Known Issues

**Production Testing Results:** [2025-12-18 Review](review/2025-12-18/README.md) - First real-world testing revealed 6 critical issues

### Critical Issues Requiring Fixes

1. **[Issue 001: PHPStan Memory Exhaustion](issue/001-phpstan-memory-exhaustion.md)** - High Priority, Low Effort
   - Memory exhaustion on large TYPO3 projects (>435 files)
   - Recommended: Dynamic memory limit based on project analysis

2. **[Issue 002: PHP CS Fixer Memory Exhaustion](issue/002-php-cs-fixer-memory-exhaustion.md)** - High Priority, Low Effort
   - Similar memory issues with large codebases
   - Recommended: Automatic memory optimization

3. **[Issue 003: Fractor YAML Parser Crash](issue/003-fractor-yaml-parser-crash.md)** - Medium Priority, Medium Effort
   - TypeError in YAML parsing crashes tool execution
   - Recommended: Automatic error recovery with validation

4. **[Issue 004: TypoScript Lint Path Option](issue/004-typoscript-lint-path-option.md)** - Medium Priority, Low Effort
   - Command interface mismatch with --path option
   - Recommended: Intelligent path discovery

5. **[Issue 005: Composer Normalize Missing](issue/005-composer-normalize-missing.md)** - High Priority, Low Effort
   - Missing dependency in target projects
   - Recommended: Bundle dependency with fallback

6. **[Issue 006: Rector Performance](issue/006-rector-performance-large-projects.md)** - Low Priority, Medium Effort
   - Performance issues on large projects (>30 seconds)
   - Recommended: Automatic performance optimization

**Result:** 5/6 tools failed completely, 1 tool severely degraded on first production use

### Upcoming Features

- **[Feature 004: Dynamic Resource Optimization](feature/004-dynamic-resource-optimization.md)** - Addresses Issues 001, 002, 006
  - Automatic project analysis and resource optimization
  - Zero-configuration memory management
  - Performance optimization based on project size
