# Issue 002: PHP CS Fixer Memory Exhaustion

**Status:** RESOLVED - Fixed by Feature 004: Dynamic Resource Optimization
**Priority:** High
**Effort:** Low (1-2h)
**Impact:** High

## Description

PHP CS Fixer crashes with memory exhaustion when analyzing large TYPO3 projects, preventing code style checking and fixing.

## Root Cause

PHP CS Fixer's tokenizer consumes too much memory when processing large codebases. The default PHP memory limit (128M) is exceeded during token analysis of 435 PHP files.

## Error Details

**Error Message:**
```
PHP Fatal error:  Allowed memory size of 134217728 bytes exhausted (tried to allocate 4096 bytes) in /path/to/project/app/vendor/friendsofphp/php-cs-fixer/src/Tokenizer/Tokens.php on line 1149
```

**Location:** PHP CS Fixer tokenizer component
**Trigger:** Running `lint:php-cs-fixer` on projects with many files

## Impact Analysis

**Affected Components:**
- PhpCsFixerLintCommand
- PhpCsFixerFixCommand
- Code style validation workflow

**User Impact:**
- Cannot check or fix code style issues
- Style inconsistencies go undetected
- Manual style checking required

**Technical Impact:**
- Code quality standards not enforced
- Inconsistent codebase style
- Developer productivity reduced

## Possible Solutions

### Solution 1: Increase Memory Limit via Environment
- **Description:** Set memory_limit environment variable before execution
- **Effort:** Low
- **Impact:** High
- **Pros:** Standard approach, works with any PHP tool
- **Cons:** May need per-project tuning

### Solution 2: Use PHP CS Fixer Memory Optimization Options
- **Description:** Enable parallel processing and cache optimizations
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Uses tool's built-in optimizations, better performance
- **Cons:** May not be sufficient for very large projects

### Solution 3: Implement File Filtering
- **Description:** Limit analysis to specific directories or file patterns
- **Effort:** Medium
- **Impact:** Medium
- **Pros:** Reduced memory usage, faster execution
- **Cons:** May miss files that need style fixes

### Solution 4: Dynamic Memory Limit with Project Analysis
- **Description:** Automatically calculate optimal memory limit based on project size analysis, reusing ProjectAnalyzer from PHPStan issue
- **Effort:** Low (reuse existing infrastructure)
- **Impact:** High
- **Pros:** Zero user configuration, scales automatically, consistent with PHPStan approach
- **Cons:** Depends on ProjectAnalyzer implementation

## Recommended Solution

**Choice:** Solution 4 - Dynamic Memory Limit (with Solution 1 as fallback)

**Rationale:** Provides seamless user experience with zero configuration. Uses shared ProjectAnalyzer utility for consistency across tools.

**Implementation Steps:**
1. Integrate ProjectAnalyzer utility for automatic project size detection
2. Calculate optimal memory limit based on file count and complexity
3. Set memory_limit environment variable before PHP CS Fixer execution
4. Add fallback to configurable memory option for edge cases
5. Enable parallel processing for performance optimization
6. Test with various project sizes to validate memory calculations

## Validation Plan

- [x] Test command execution on large project without crash
- [x] Verify all files are processed successfully
- [x] Confirm style analysis quality unchanged
- [x] Test both lint and fix operations

## Resolution Summary

**Fixed by:** Feature 004: Dynamic Resource Optimization
**Resolution Date:** 2025-12-18
**Implementation:**
- Automatic memory limit calculation based on project analysis
- 460M memory allocation for PHP CS Fixer on large projects
- Fixed executeProcess() method to correctly handle memory limits
- Successfully processes 174 PHP files without memory exhaustion
- Parallel processing enabled automatically where beneficial

## Dependencies

None - uses standard PHP memory management

## Workarounds

Users can temporarily increase PHP memory limit:
```bash
php -d memory_limit=512M app/vendor/bin/php-cs-fixer fix --dry-run
```

## Related Issues

- 001-phpstan-memory-exhaustion (similar memory issue)
- Need general memory management strategy for all tools
