# Issue 006: Rector Performance on Large Projects

**Status:** Open  
**Priority:** Low  
**Effort:** Medium (3-8h)  
**Impact:** Medium

## Description

Rector analysis takes excessively long time (>30 seconds) on large TYPO3 projects, making it impractical for regular development workflow.

## Root Cause

Rector performs comprehensive static analysis across the entire codebase including dependencies. Large TYPO3 projects with thousands of files require significant processing time without optimization.

## Error Details

**Error Message:**
```
[No error - tool works but very slowly]
Processing 3824+ files takes >30 seconds for dry-run analysis
```

**Location:** Rector analysis engine  
**Trigger:** Running `lint:rector` on large projects without scoping

## Impact Analysis

**Affected Components:**
- RectorLintCommand
- RectorFixCommand
- Development workflow efficiency

**User Impact:**
- Long wait times disrupt development flow
- Developers may skip quality checks due to performance
- Reduced adoption of automated refactoring

**Technical Impact:**
- Slower CI/CD pipeline execution
- Increased resource usage on development machines
- Potential timeout issues in automated environments

## Possible Solutions

### Solution 1: Add Path Scoping
- **Description:** Allow targeting specific directories instead of entire project
- **Effort:** Low
- **Impact:** High
- **Pros:** Faster execution, focused analysis, existing Rector feature
- **Cons:** May miss cross-directory dependencies

### Solution 2: Implement Parallel Processing
- **Description:** Enable Rector's parallel processing capabilities
- **Effort:** Low
- **Impact:** Medium
- **Pros:** Uses multiple CPU cores, existing Rector feature
- **Cons:** May not help with I/O-bound operations

### Solution 3: Add Progress Indicators
- **Description:** Show progress during long-running operations
- **Effort:** Low
- **Impact:** Low
- **Pros:** Better user experience, no functional changes needed
- **Cons:** Doesn't improve actual performance

### Solution 4: Implement Caching Strategy
- **Description:** Cache analysis results to avoid re-processing unchanged files
- **Effort:** Medium
- **Impact:** High
- **Pros:** Dramatically faster subsequent runs, Rector supports caching
- **Cons:** May miss some dependency changes

### Solution 5: Automatic Performance Optimization Based on Project Analysis
- **Description:** Automatically configure Rector with optimal settings based on project size analysis, no user configuration required
- **Effort:** Medium
- **Impact:** High
- **Pros:** Zero user configuration, optimal performance automatically, reuses ProjectAnalyzer infrastructure
- **Cons:** May need calibration for different project types

## Recommended Solution

**Choice:** Solution 5 - Automatic Performance Optimization (combining Solutions 1, 2, and 4)

**Rationale:** Provides optimal performance automatically based on project characteristics. Users get best performance without needing to understand Rector optimization options.

**Implementation Steps:**
1. Integrate ProjectAnalyzer to assess project size and complexity
2. Automatically enable --parallel processing for projects with >100 files
3. Configure optimal cache directory based on project structure
4. Automatically scope analysis to packages/ directory for TYPO3 projects
5. Add progress output for operations estimated to take >10 seconds
6. Provide performance summary after completion with optimization details applied

## Validation Plan

- [ ] Test execution time improvement with optimizations
- [ ] Verify analysis quality remains unchanged
- [ ] Confirm caching works correctly across runs
- [ ] Test parallel processing stability

## Dependencies

None - uses existing Rector capabilities

## Workarounds

Users can run Rector with optimizations directly:
```bash
app/vendor/bin/rector --parallel --dry-run packages/zug-sitepackage/
```

## Related Issues

- General performance optimization strategy needed for all tools
- Timeout handling for CI/CD environments