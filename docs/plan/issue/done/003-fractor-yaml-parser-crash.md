# Issue 003: Fractor YAML Parser Crash

**Status:** RESOLVED - Fixed with YAML validation and error recovery
**Priority:** Medium
**Effort:** Medium (3-8h)
**Impact:** Medium

## Description

Fractor crashes with TypeError when parsing YAML files during TypoScript modernization process, preventing automated refactoring.

## Root Cause

Fractor's YAML parser expects array return value but receives string, indicating incompatible data format or corrupted YAML file in the target project.

## Error Details

**Error Message:**
```
PHP Fatal error:  Uncaught TypeError: a9f\FractorYaml\SymfonyYamlParser::parse(): Return value must be of type array, string returned in /path/to/project/app/vendor/a9f/fractor-yaml/src/SymfonyYamlParser.php on line 18
```

**Location:** Fractor YAML parser component
**Trigger:** Processing YAML files during fractor analysis (at file 47/3824)

## Impact Analysis

**Affected Components:**
- FractorLintCommand
- FractorFixCommand
- TypoScript modernization workflow

**User Impact:**
- Cannot modernize TypoScript configuration
- Manual refactoring required
- Project technical debt accumulates

**Technical Impact:**
- Outdated TypoScript patterns remain
- Configuration inconsistencies
- Potential compatibility issues

## Possible Solutions

### Solution 1: Add YAML Validation
- **Description:** Pre-validate YAML files before processing, skip invalid ones
- **Effort:** Medium
- **Impact:** High
- **Pros:** Graceful error handling, continues processing other files
- **Cons:** May miss important configuration files

### Solution 2: Update Fractor Version
- **Description:** Check if newer Fractor version fixes this parser issue
- **Effort:** Low
- **Impact:** High
- **Pros:** May resolve underlying bug, get other improvements
- **Cons:** May introduce new breaking changes

### Solution 3: Implement Error Recovery
- **Description:** Catch TypeError and continue with warning message
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Prevents complete failure, provides diagnostic info
- **Cons:** Doesn't fix underlying issue

### Solution 4: Exclude Problematic Files
- **Description:** Identify and exclude specific YAML files causing issues
- **Effort:** Medium
- **Impact:** Low
- **Pros:** Quick workaround, minimal code changes
- **Cons:** Doesn't address root cause, may miss important files

### Solution 5: Automatic Error Recovery with YAML Validation
- **Description:** Pre-validate YAML files and automatically skip corrupted ones while providing detailed diagnostic information
- **Effort:** Medium
- **Impact:** High
- **Pros:** Zero user configuration, graceful error handling, diagnostic information, continues processing
- **Cons:** May skip important configuration files that could be manually fixed

## Recommended Solution

**Choice:** Solution 5 - Automatic Error Recovery (with Solution 2 as prerequisite)

**Rationale:** Update Fractor first, then add robust error recovery that works without user intervention while providing helpful diagnostic information.

**Implementation Steps:**
1. Update Fractor to latest version to resolve known YAML parser issues
2. Implement pre-YAML validation before Fractor processing
3. Add automatic skipping of problematic YAML files with detailed logging
4. Continue processing remaining files to maximize analysis coverage
5. Provide summary of skipped files with suggestions for manual review
6. Add option to export problematic file list for user investigation

## Validation Plan

- [x] Test fractor execution without crash
- [x] Verify processing continues after YAML errors
- [x] Confirm useful error messages are shown
- [x] Test that valid YAML files are still processed correctly

## Resolution Summary

**Fixed by:** YAML validation and error recovery implementation
**Resolution Date:** 2025-12-18
**Implementation:**
- Added YamlValidator utility for pre-validating YAML files
- Enhanced FractorLintCommand and FractorFixCommand with automatic error recovery
- Integrated with Dynamic Resource Optimization (368M memory allocation)
- Successfully processes valid files while identifying problematic ones
- Provides detailed diagnostic information about YAML issues
- Zero-configuration solution - works automatically without user input

**Test Results:**
- Fractor processes 2 files with changes successfully
- Identifies 1 problematic YAML file (Services.yaml with custom tags)
- Provides clear user guidance for manual file review
- No more fatal crashes - graceful error recovery working

## Dependencies

- May require Fractor package update
- Need error logging mechanism

## Workarounds

Users can run Fractor directly and exclude YAML processing:
```bash
app/vendor/bin/fractor process --dry-run --no-yaml
```

## Related Issues

- Need robust error handling strategy for all tools
- YAML file validation may be needed project-wide
