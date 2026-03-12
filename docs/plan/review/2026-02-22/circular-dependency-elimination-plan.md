# Circular Dependency Elimination Plan

**Date**: 2026-02-22
**Issue**: Architecture refactoring to eliminate circular dependencies in path resolution services
**Status**: COMPLETED

## Architecture Refactoring Plan: Eliminate Circular Dependencies

### Phase 1: Refactor SecurityService (Pure String Sanitization) - COMPLETED
- [x] **Remove FilesystemService dependency** from SecurityService constructor
- [x] **Move all filesystem operations** out of SecurityService to calling classes
- [x] **Keep SecurityService focused on:**
  - [x] Path string sanitization (`sanitizePath()`)
  - [x] Environment variable validation (`getEnvironmentVariable()`)
  - [x] Path content safety checks (`isPathContentSafe()`)
  - [x] Configuration file type validation (string-based only)

### Phase 2: Update FilesystemService (Add Security Integration) - COMPLETED
- [x] **Add SecurityService dependency** to FilesystemService constructor
- [x] **Sanitize all paths** before filesystem operations using `SecurityService::sanitizePath()`
- [x] **Ensure all file operations** go through security validation first
- [x] **Keep FilesystemService focused on:** Pure filesystem operations with built-in security

### Phase 3: Fix PathResolutionService (Eliminate Optional Dependencies) - COMPLETED
- [x] **Make SecurityService dependency mandatory** (remove nullable `?SecurityService`)
- [x] **Add FilesystemService dependency** for filesystem access/checks
- [x] **Use SecurityService for all path sanitization** before resolution
- [x] **Use FilesystemService for all filesystem access** (file exists, readable, etc.)
- [x] **Remove direct filesystem calls** (file_exists, is_readable) - go through FilesystemService

### Phase 4: Update Consumer Classes - COMPLETED
- [x] **Update all classes** using FilesystemService (automatically get security validation)
- [x] **Fix BaseCommand dependency injection** to match new constructor signatures
- [x] **Update unit tests** to reflect new dependencies and behavior

### Benefits of This Architecture - ACHIEVED
- [x] **No circular dependencies**: Clean dependency flow
- [x] **Consistent security**: All filesystem access automatically sanitized
- [x] **Single responsibility**: Each service has clear, focused role
- [x] **No optional dependencies**: Predictable, consistent behavior
- [x] **Centralized security**: One place for all path sanitization logic

## Current Architecture Issues

1. **SecurityService** currently depends on `FilesystemService` - this creates the circular dependency problem
2. **PathResolutionService** has optional `SecurityService` dependency - this creates inconsistent behavior
3. **FilesystemService** does no path sanitization - consumers must handle security independently
4. Multiple scattered path normalization methods with different behaviors

## Target Architecture Vision

```
FilesystemService -> SecurityService (for sanitization only)
PathResolutionService -> SecurityService (for sanitization) + FilesystemService (for access)
SecurityService: Pure string sanitization (no filesystem dependencies)
```

## Implementation Sequence

1. **Phase 1**: SecurityService becomes pure string validator
2. **Phase 2**: FilesystemService integrates SecurityService for all operations
3. **Phase 3**: PathResolutionService uses both services with mandatory dependencies
4. **Phase 4**: Update all consuming classes and tests

This approach ensures clean separation of concerns and eliminates all circular dependency risks.
