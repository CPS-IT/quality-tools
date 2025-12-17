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

- **[002 - Base Command](feature/002-base-command.md)** *(2-3 hours)*  
  Single base command class with shared functionality for all tools

- **[003 - Tool Commands](feature/003-tool-commands.md)** *(4-6 hours)*  
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

- **Planning Phase:** Complete with simplified feature breakdown
- **Core Implementation Phase:** Ready for implementation (8-12 hours)
- **Polish Phase:** Testing, documentation, and final release
