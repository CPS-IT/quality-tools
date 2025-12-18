# Issue 004: TypoScript Lint Path Option Not Supported

**Status:** RESOLVED - Fixed with hybrid path handling approach
**Priority:** Medium
**Effort:** Low (1-2h)
**Impact:** Medium

## Description

TypoScript linter rejects --path option that our CLI attempts to use, preventing targeted analysis of specific directories.

## Root Cause

Our TypoScriptLintCommand inherits --path option from BaseCommand but the underlying typoscript-lint tool doesn't support this option. The tool uses configuration file path discovery and accepts paths as positional arguments instead.

## Error Details

**Error Message:**
```
The "--path" option does not exist.

lint [-c|--config CONFIG] [-f|--format FORMAT] [-o|--output OUTPUT] [-e|--exit-code] [--fail-on-warnings] [--] [<paths>...]
```

**Location:** TypoScript lint tool command line interface
**Trigger:** Running `lint:typoscript` with inherited --path option

## Impact Analysis

**Affected Components:**
- TypoScriptLintCommand
- Path-based analysis functionality

**User Impact:**
- Cannot specify custom paths for TypoScript analysis
- Must analyze entire project instead of targeted directories
- Inconsistent interface across tools

**Technical Impact:**
- Longer analysis times on large projects
- Interface inconsistency between tools
- Potential confusion for users

## Possible Solutions

### Solution 1: Remove Path Option Usage
- **Description:** Override path handling in TypoScriptLintCommand to not use --path
- **Effort:** Low
- **Impact:** High
- **Pros:** Quick fix, aligns with tool's actual interface
- **Cons:** Loses path targeting functionality

### Solution 2: Convert Path to Positional Arguments
- **Description:** Transform --path option value into positional arguments for the tool
- **Effort:** Low
- **Impact:** High
- **Pros:** Maintains path functionality, correct tool usage
- **Cons:** Requires different argument handling

### Solution 3: Use Tool's Built-in Path Discovery
- **Description:** Let tool discover TypoScript files automatically from project root
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Simple implementation, follows tool design
- **Cons:** No path targeting, may analyze unintended files

### Solution 4: Use Configuration-Based Path Discovery (Zero Configuration)
- **Description:** Remove --path option usage and rely on typoscript-lint.yml configuration for path discovery
- **Effort:** Low
- **Impact:** High
- **Pros:** Uses tool's native path discovery, zero user configuration, already configured optimally
- **Cons:** Users cannot easily override paths without modifying configuration

### Solution 5: Hybrid Approach - Configuration + Positional Arguments
- **Description:** Use configuration file by default, allow positional path arguments when --path is specified
- **Effort:** Low
- **Impact:** High
- **Pros:** Best of both worlds - zero config default with manual override capability
- **Cons:** Slightly more complex argument handling

## Recommended Solution

**Choice:** Solution 5 - Hybrid Approach

**Rationale:** The current typoscript-lint.yml configuration is already optimal with glob patterns for standard TYPO3 paths. Default behavior should use this configuration, while allowing manual path specification for edge cases.

**Implementation Steps:**
1. Remove --path option from TypoScript lint command execution
2. Use configuration file for default path discovery (already correctly configured)
3. When user specifies --path, convert to positional arguments for the tool
4. Add informational output showing which paths are being analyzed
5. Document that configuration file handles standard TYPO3 project structures automatically

## Validation Plan

- [x] Test command execution with custom path succeeds
- [x] Verify tool analyzes only specified directory
- [x] Confirm default behavior (no path) works correctly
- [x] Test with multiple paths if supported

## Resolution Summary

**Fixed by:** Hybrid path handling implementation in TypoScriptLintCommand
**Resolution Date:** 2025-12-18
**Implementation:**
- Removed problematic --path option usage from command execution
- Added positional argument support when --path is specified by user
- Default behavior uses configuration file path discovery (packages/**/Configuration/TypoScript)
- Custom path validation with informative user feedback
- Comprehensive test coverage for all scenarios

**Test Results:**
- Default execution: 245 files analyzed using configuration-based discovery
- Custom path execution: 90 files analyzed in specified directory
- No "--path option does not exist" error
- Proper user feedback about path discovery method being used
- All unit tests passing

## Dependencies

None - uses existing tool capabilities correctly

## Configuration Analysis

The current `config/typoscript-lint.yml` is already optimally configured:

```yaml
paths:
  - packages/**/Configuration/TypoScript
  - packages/**/Configuration/TSconfig
filePatterns:
  - "*.typoscript"
  - "*.tsconfig"
```

This configuration automatically discovers TypoScript files in standard TYPO3 locations using glob patterns, providing zero-configuration operation for standard projects.

## Workarounds

Users can run typoscript-lint directly with proper syntax:
```bash
app/vendor/bin/typoscript-lint packages/zug-sitepackage/
```

Or rely on the configuration file (recommended):
```bash
app/vendor/bin/typoscript-lint -c config/typoscript-lint.yml
```

## Related Issues

- Need consistent path handling strategy across all tools
- Tool interface documentation should specify argument patterns
