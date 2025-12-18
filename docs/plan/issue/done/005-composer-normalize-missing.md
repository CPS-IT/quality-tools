# Issue 005: Composer Normalize Executable Missing

**Status:** RESOLVED - Fixed by using 'composer normalize' plugin instead of executable
**Priority:** High
**Effort:** Low (1-2h)
**Impact:** Medium

## Description

Composer normalize commands fail because the composer-normalize executable is not found in the target project's vendor/bin directory.

## Root Cause

The composer-normalize package is not installed in the target TYPO3 project. Our tools assume this dependency is available, but it's not a standard Composer component and must be explicitly installed.

## Error Details

**Error Message:**
```
sh: /path/to/project/app/vendor/bin/composer-normalize: No such file or directory
sh: line 0: exec: /path/to/project/app/vendor/bin/composer-normalize: cannot execute: No such file or directory
```

**Location:** ComposerLintCommand and ComposerFixCommand
**Trigger:** Running `lint:composer` or `fix:composer` on projects without composer-normalize

## Impact Analysis

**Affected Components:**
- ComposerLintCommand
- ComposerFixCommand
- Composer validation workflow

**User Impact:**
- Cannot validate or normalize composer.json files
- Inconsistent composer.json formatting
- Manual composer.json maintenance required

**Technical Impact:**
- No composer.json standardization
- Potential project configuration issues
- Build process inconsistencies

## Possible Solutions

### Solution 1: Add Dependency Check
- **Description:** Check if composer-normalize exists before execution, show helpful error
- **Effort:** Low
- **Impact:** High
- **Pros:** Clear user guidance, prevents confusing errors
- **Cons:** Doesn't solve the missing functionality

### Solution 2: Make Composer Normalize Optional
- **Description:** Skip composer commands gracefully when tool is not available
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Graceful degradation, doesn't break other tools
- **Cons:** Silent failure may hide important issues

### Solution 3: Bundle Composer Normalize
- **Description:** Add composer-normalize as dependency of quality-tools package
- **Effort:** Low
- **Impact:** High
- **Pros:** Ensures availability, no user setup required
- **Cons:** Increases package size, forces dependency on all users

### Solution 4: Provide Installation Instructions
- **Description:** Document how to install composer-normalize and check in setup
- **Effort:** Low
- **Impact:** Low
- **Pros:** User has control over dependencies
- **Cons:** Requires manual setup, may be forgotten

### Solution 5: Bundle Dependency with Automatic Fallback
- **Description:** Bundle composer-normalize as required dependency and provide intelligent fallback when unavailable
- **Effort:** Low
- **Impact:** High
- **Pros:** Zero user configuration, always available, graceful degradation
- **Cons:** Slightly increased package size

## Recommended Solution

**Choice:** Solution 5 - Bundle Dependency with Automatic Fallback

**Rationale:** Ensures composer-normalize is always available without requiring users to manually install dependencies. Provides the best zero-configuration experience.

**Implementation Steps:**
1. Add `ergebnis/composer-normalize` as required dependency in quality-tools composer.json
2. Update ComposerLintCommand and ComposerFixCommand to use bundled executable
3. Add intelligent fallback that checks for existing composer-normalize in target project first
4. Provide clear informational messages about which executable is being used
5. Update documentation to note that composer normalization is included by default
6. Test with projects that have and don't have composer-normalize already installed

## Validation Plan

- [x] Test commands work when composer-normalize is available
- [x] Verify helpful error message when composer.json missing
- [x] Confirm bundled dependency works correctly
- [x] Test in fresh project without existing composer-normalize

## Resolution Summary

**Fixed by:** Changing from executable approach to Composer plugin approach
**Resolution Date:** 2025-12-18
**Implementation:**
- Changed ComposerLintCommand to use 'composer normalize --dry-run' instead of '/vendor/bin/composer-normalize'
- Changed ComposerFixCommand to use 'composer normalize' instead of '/vendor/bin/composer-normalize'
- Added composer.json file existence validation with clear error messages
- Leveraged existing ergebnis/composer-normalize dependency (already bundled in composer.json)
- Added informative user feedback about normalization process
- Updated test coverage for new command structure

**Test Results:**
- lint:composer: Successfully validates composer.json normalization status
- fix:composer: Successfully normalizes composer.json files
- Path targeting: Works correctly with --path option
- Error handling: Proper error messages for missing composer.json files
- Plugin integration: Uses composer-normalize as intended (plugin not executable)

## Dependencies

- Need to add ergebnis/composer-normalize to package dependencies
- Documentation update required

## Workarounds

Users can manually install composer-normalize:
```bash
composer require --dev ergebnis/composer-normalize
```

## Related Issues

- General dependency management strategy needed
- Tool availability checking should be standardized
