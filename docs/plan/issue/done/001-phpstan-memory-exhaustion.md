# Issue 001: PHPStan Memory Exhaustion

**Status:** RESOLVED - Fixed by Feature 004: Dynamic Resource Optimization
**Priority:** High
**Effort:** Low (1-2h)
**Impact:** High

## Description

PHPStan crashes with memory exhaustion error when analyzing large TYPO3 projects, preventing static analysis from completing.

## Root Cause

PHPStan's default memory limit (128M) is insufficient for analyzing large TYPO3 projects with thousands of files. The codebase being analyzed has 435 PHP files and 3824 total files.

## Error Details

**Error Message:**
```
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 15208448 bytes) in phar:///path/to/project/app/vendor/phpstan/phpstan/phpstan.phar/src/Cache/FileCacheStorage.php on line 73

PHPStan process crashed because it reached configured PHP memory limit: 128M
Increase your memory limit in php.ini or run PHPStan with --memory-limit CLI option.
```

**Location:** PHPStan cache storage component
**Trigger:** Running `lint:phpstan` on large projects

## Impact Analysis

**Affected Components:**
- PhpStanCommand
- Static analysis workflow
- Quality assurance pipeline

**User Impact:**
- Cannot perform static analysis on large projects
- Quality checks incomplete
- Developer workflow interrupted

**Technical Impact:**
- No static analysis coverage
- Potential bugs go undetected
- CI/CD pipeline may fail

## Possible Solutions

### Solution 1: Add Memory Limit Option
- **Description:** Add --memory-limit parameter to PHPStan command execution
- **Effort:** Low
- **Impact:** High
- **Pros:** Simple fix, standard PHPStan feature, configurable
- **Cons:** May need tuning per project size

### Solution 2: Increase Global PHP Memory Limit
- **Description:** Modify php.ini or use ini_set to increase memory limit
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Fixes all memory issues system-wide
- **Cons:** Affects all PHP processes, not tool-specific

### Solution 3: Implement Chunked Analysis
- **Description:** Split analysis into smaller batches of files
- **Effort:** High
- **Impact:** High
- **Pros:** Scalable solution, works with any project size
- **Cons:** Complex implementation, may miss cross-file dependencies

### Solution 4: Dynamic Memory Limit Based on Project Analysis
- **Description:** Automatically analyze project size (file count, lines of code) and set optimal memory limit without user configuration
- **Effort:** Medium
- **Impact:** High
- **Pros:** Zero user configuration, scales automatically, reusable for other tools, optimal resource usage
- **Cons:** Initial complexity, may need calibration across different project types

## Recommended Solution

**Choice:** Solution 4 - Dynamic Memory Limit (with Solution 1 as fallback)

**Rationale:** This provides the best user experience with zero configuration while being reusable across all memory-intensive tools. Solution 1 serves as a simple fallback for edge cases.

PHPStan has built-in --memory-limit option designed for this exact issue. It's the standard approach and most reliable.

**Implementation Steps:**
1. Create `ProjectAnalyzer` utility class to analyze project characteristics
2. Add method to calculate optimal memory limit based on file count and complexity
3. Integrate dynamic memory allocation in PhpStanCommand before execution
4. Add fallback to configurable memory limit option for edge cases
5. Test across different project sizes to calibrate algorithm
6. Apply same strategy to PHP CS Fixer and other memory-intensive tools

## Validation Plan

- [x] Test command execution on large TYPO3 project without crash
- [x] Verify analysis completes successfully
- [x] Confirm output quality unchanged
- [x] Test with different memory limits to find optimal value

## Resolution Summary

**Fixed by:** Feature 004: Dynamic Resource Optimization
**Resolution Date:** 2025-12-18
**Implementation:**
- ProjectAnalyzer utility automatically analyzes project size (1,001 files, 174 PHP files)
- MemoryCalculator dynamically sets optimal memory limits (552M for PHPStan)
- Zero-configuration solution - works automatically without user input
- Successfully tested on large TYPO3 project without memory exhaustion

## Dependencies

None - PHPStan already supports this option

## Workarounds

Users can temporarily modify php.ini memory_limit or run PHPStan directly:
```bash
app/vendor/bin/phpstan analyse --memory-limit=512M
```

## Related Issues

- Similar memory issues may affect other tools (PHP CS Fixer)
- Large project optimization strategies needed
