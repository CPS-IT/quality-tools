# Issue 004: TypoScript Lint Path Option Not Supported

**Status:** Open  
**Priority:** Medium  
**Effort:** Low (1-2h)  
**Impact:** Medium

## Description

TypoScript linter rejects --path option that our CLI attempts to use, preventing targeted analysis of specific directories.

## Root Cause

Our TypoScriptLintCommand inherits --path option from BaseCommand but the underlying typoscript-lint tool doesn't support this option. The tool expects paths as positional arguments instead.

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

### Solution 4: Intelligent Path Discovery with Zero Configuration
- **Description:** Automatically discover TypoScript files in common TYPO3 locations without requiring user to specify paths
- **Effort:** Low
- **Impact:** High
- **Pros:** Zero user configuration, works out-of-the-box for standard TYPO3 projects, consistent behavior
- **Cons:** May not cover non-standard project structures

## Recommended Solution

**Choice:** Solution 4 - Intelligent Path Discovery (with Solution 2 as fallback)

**Rationale:** Provides the best user experience by automatically finding TypoScript files in standard TYPO3 locations. Users only need to specify paths for non-standard projects.

**Implementation Steps:**
1. Create automatic TypoScript file discovery for common TYPO3 paths (packages/*/Configuration/TypoScript/, config/sites/)
2. Use discovered paths as positional arguments for typoscript-lint
3. Allow manual path override via --path option converted to positional arguments
4. Add informational output showing which paths are being analyzed
5. Test with various TYPO3 project structures to ensure comprehensive coverage

## Validation Plan

- [ ] Test command execution with custom path succeeds
- [ ] Verify tool analyzes only specified directory
- [ ] Confirm default behavior (no path) works correctly
- [ ] Test with multiple paths if supported

## Dependencies

None - uses existing tool capabilities correctly

## Workarounds

Users can run typoscript-lint directly with proper syntax:
```bash
app/vendor/bin/typoscript-lint packages/zug-sitepackage/
```

## Related Issues

- Need consistent path handling strategy across all tools
- Tool interface documentation should specify argument patterns