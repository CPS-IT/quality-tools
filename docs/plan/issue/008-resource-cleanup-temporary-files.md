# Issue 008: Resource Cleanup for Temporary Files

**Status:** Open  
**Priority:** Critical  
**Effort:** Medium (3-8h)  
**Impact:** High

## Description

The PHPStan command creates temporary configuration files without implementing proper cleanup mechanisms, leading to accumulation of temporary files and potential resource leaks in long-running environments.

## Root Cause

The PhpStanCommand::createTemporaryPhpStanConfig() method creates temporary files using tempnam() but lacks corresponding cleanup logic. Additionally, test environments show file permission warnings indicating potential cleanup issues across the codebase.

## Error Details

**Error Message:**
```
file_put_contents(...): Failed to open stream: Permission denied
Temporary files accumulate without cleanup in /tmp directory
```

**Location:** src/Command/PhpStanCommand.php - createTemporaryPhpStanConfig() method  
**Trigger:** Running PHPStan commands, especially multiple consecutive runs

## Impact Analysis

**Affected Components:**
- PhpStanCommand temporary file creation
- Test suite cleanup mechanisms
- Long-running CI/CD environments

**User Impact:**
- Disk space consumption from accumulated temporary files
- Potential permission issues in shared environments
- Unreliable test runs due to cleanup failures

**Technical Impact:**
- Resource leaks in production environments
- Test isolation problems
- Security concerns with lingering temporary files

## Possible Solutions

### Solution 1: Disposable Pattern Implementation
- **Description:** Implement disposable pattern with automatic cleanup using PHP destructors and try-finally blocks
- **Effort:** Medium
- **Impact:** High effectiveness for resource management
- **Pros:** Automatic cleanup, exception-safe, follows RAII principles
- **Cons:** Requires refactoring existing temporary file usage

### Solution 2: Temporary File Registry
- **Description:** Create a registry to track all temporary files and clean them up at process termination
- **Effort:** Medium
- **Impact:** Medium effectiveness, centralized management
- **Pros:** Centralized cleanup logic, works with existing code
- **Cons:** Additional complexity, requires global state management

## Recommended Solution

**Choice:** Disposable Pattern Implementation with registry fallback

Implement disposable pattern for new temporary file usage while maintaining a cleanup registry for safety.

**Implementation Steps:**
1. Create TemporaryFile value object with automatic cleanup
2. Implement DisposableTemporaryFile with destructor cleanup
3. Refactor PhpStanCommand to use new temporary file abstraction
4. Add process shutdown hooks for emergency cleanup
5. Fix test cleanup issues with proper teardown methods
6. Add logging for temporary file operations

## Validation Plan

- [ ] No temporary files remain after command execution
- [ ] All tests pass with proper cleanup in teardown methods
- [ ] Long-running test suites don't accumulate temporary files
- [ ] Exception scenarios properly clean up temporary files
- [ ] File permissions are correctly set for temporary files

## Dependencies

- May require Symfony Filesystem component for robust file operations
- Consider integration with existing configuration management

## Workarounds

Manually clean up temporary files after tool execution:
```bash
# Find and remove old temporary files
find /tmp -name "phpstan_*" -mtime +1 -delete
```

## Related Issues

- Security hardening (issue 009) will benefit from proper temporary file management
- Test improvements will require reliable cleanup mechanisms
