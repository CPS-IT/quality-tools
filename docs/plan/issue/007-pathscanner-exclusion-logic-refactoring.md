# Issue 007: PathScanner Exclusion Logic Refactoring

**Status:** Open  
**Priority:** Critical  
**Effort:** High (1-3d)  
**Impact:** Critical

## Description

The PathScanner::applyExclusions() method exhibits high cyclomatic complexity with deeply nested conditional logic, making it difficult to test, maintain, and debug. The method handles multiple responsibilities including vendor path exemptions, pattern matching, and exclusion logic.

## Root Cause

The exclusion logic combines multiple concerns in a single method:
- Vendor path exemption handling
- Pattern-based exclusions
- Nested conditional logic with multiple return points
- Mixed abstraction levels in the same closure

## Error Details

**Error Message:**
```php
// PathScanner::applyExclusions() - High cyclomatic complexity detected
private function applyExclusions(array $paths, array $excludePatterns, array $explicitVendorPaths = []): array
{
    return array_filter($paths, function ($path) use ($excludePatterns, $explicitVendorPaths) {
        // Complex nested logic with 15+ decision points
    });
}
```

**Location:** src/Utility/PathScanner.php:applyExclusions()  
**Trigger:** Path resolution with vendor paths and exclusion patterns

## Impact Analysis

**Affected Components:**
- PathScanner service
- All command classes using path resolution
- Multi-path scanning functionality
- Caching mechanism for path resolution

**User Impact:**
- Unpredictable behavior with complex path patterns
- Difficult to troubleshoot path resolution issues
- Performance degradation with large path sets

**Technical Impact:**
- High maintenance cost for path logic changes
- Difficulty writing comprehensive tests
- Risk of introducing bugs in path resolution
- Reduced code reliability and predictability

## Possible Solutions

### Solution 1: Strategy Pattern with Dedicated Filter Classes
- **Description:** Extract exclusion logic into separate PathExclusionFilter classes
- **Effort:** High
- **Impact:** Critical - significantly improves maintainability and testability
- **Pros:** Clear separation of concerns, easy to test individual strategies, extensible
- **Cons:** More files to maintain, requires comprehensive refactoring

### Solution 2: Early Return Flattening
- **Description:** Restructure existing method to use early returns and reduce nesting
- **Effort:** Medium
- **Impact:** Medium - improves readability but doesn't address core complexity
- **Pros:** Less refactoring needed, immediate improvement
- **Cons:** Still maintains multiple responsibilities in one method

### Solution 3: Functional Decomposition
- **Description:** Break method into smaller, focused private methods
- **Effort:** Medium
- **Impact:** High - improves testability while keeping existing structure
- **Pros:** Incremental improvement, easier to test individual parts
- **Cons:** May still have some coupling issues

## Recommended Solution

**Choice:** Strategy Pattern with Dedicated Filter Classes

This provides the cleanest separation of concerns and highest long-term maintainability.

**Implementation Steps:**
1. Create PathExclusionFilter interface
2. Implement VendorPathFilter for vendor-specific logic
3. Implement PatternExclusionFilter for general pattern matching
4. Create CompositePathFilter to chain filters together
5. Refactor PathScanner to use filter chain
6. Add comprehensive unit tests for each filter
7. Update integration tests to verify end-to-end behavior

## Validation Plan

- [ ] Unit tests for each filter class achieve 100% coverage
- [ ] Integration tests verify same behavior as existing implementation
- [ ] Performance tests show no regression in path resolution speed
- [ ] Edge case testing with complex vendor path scenarios
- [ ] Verify caching mechanism still works correctly

## Dependencies

- No external dependencies
- May require updates to existing path resolution tests

## Workarounds

- Use simpler exclusion patterns to avoid triggering complex logic paths
- Manually verify vendor paths are correctly handled
- Add debug logging to track path resolution decisions

## Related Issues

- Issue 011: Performance optimization of path resolution caching
- Issue 016: Configuration validation for path patterns
- Issue 015: Testing infrastructure improvements for complex scenarios
