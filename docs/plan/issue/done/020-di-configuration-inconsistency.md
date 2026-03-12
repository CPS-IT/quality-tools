# Issue 020: Inconsistent Dependency Injection Configuration for Command ConfigurationLoader

**Status:** done
**Priority:** Medium
**Effort:** Low (1-2h)
**Impact:** Medium

## Description

Commands in the quality tools package have inconsistent dependency injection configuration for ConfigurationLoader. Some commands are explicitly configured with ConfigurationLoader DI in services.yaml, while others rely on BaseCommand's default mechanism, creating potential inconsistencies in configuration loading behavior.

## Root Cause

After the Step 6.1 refactoring that unified the configuration system, not all commands were updated to use explicit ConfigurationLoader dependency injection in services.yaml. This creates an inconsistent approach where some commands get their ConfigurationLoader injected while others instantiate it themselves.

## Error Details

**Error Message:**
```
No explicit error - this is an architectural inconsistency
```

**Location:** `config/services.yaml`
**Trigger:** Command instantiation and configuration loading

## Impact Analysis

**Affected Components:**
- PhpCsFixerLintCommand, PhpCsFixerFixCommand
- ComposerLintCommand, ComposerFixCommand
- FractorLintCommand, FractorFixCommand
- TypoScriptLintCommand

**User Impact:**
- Potential inconsistent behavior across commands
- Harder to maintain and debug configuration issues
- Inconsistent service lifecycle management

**Technical Impact:**
- Mixed dependency injection patterns
- Potential for different ConfigurationLoader instances with different state
- Reduced testability for unconfigured commands

## Possible Solutions

### Solution 1: Add Explicit DI Configuration for All Commands
- **Description:** Add ConfigurationLoader DI configuration for all missing commands in services.yaml
- **Effort:** Low
- **Impact:** High - ensures consistency across all commands
- **Pros:** Clean, consistent architecture; better testability; explicit dependencies
- **Cons:** Slightly more verbose configuration

### Solution 2: Remove DI Configuration and Use BaseCommand Default
- **Description:** Remove explicit ConfigurationLoader DI and let all commands use BaseCommand's default mechanism
- **Effort:** Low
- **Impact:** Medium - creates consistency but moves away from DI best practices
- **Pros:** Less configuration needed
- **Cons:** Reduces control over dependency injection; harder to test; less explicit

## Recommended Solution

**Choice:** Solution 1 - Add explicit DI configuration for all commands

This maintains consistency with the existing architecture and follows dependency injection best practices. It also makes the system more testable and maintainable.

**Implementation Steps:**
1. Add ConfigurationLoader DI configuration for PhpCsFixerLintCommand and PhpCsFixerFixCommand
2. Add ConfigurationLoader DI configuration for ComposerLintCommand and ComposerFixCommand
3. Add ConfigurationLoader DI configuration for FractorLintCommand and FractorFixCommand
4. Add ConfigurationLoader DI configuration for TypoScriptLintCommand
5. Verify all commands now use consistent DI pattern

## Validation Plan

- [x] All commands in services.yaml have explicit ConfigurationLoader DI configuration
- [x] Unit tests pass for all affected commands
- [x] Integration tests verify consistent configuration loading behavior
- [x] No regression in command functionality

## Dependencies

- Completion of Step 6.1 refactoring (already done)
- Unified ConfigurationLoader class (already implemented)

## Workarounds

No immediate workaround needed as commands currently function correctly through BaseCommand's fallback mechanism.

## Related Issues

- Issue 019: Configuration Class Hierarchy Simplification (Step 6.1)
- Issue 021: Missing Integration Tests for Command Path Configuration
