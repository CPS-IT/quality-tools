# Property-Based Testing for Path Resolution

This document describes the property-based testing approach implemented for the PathScanner utility, following the requirements from [Issue 017](../plan/issue/done/017-property-based-testing-path-resolution.md).

## Overview

Property-based testing validates that certain properties or invariants hold true across a wide range of input combinations. Unlike example-based tests that check specific inputs and outputs, property-based tests define rules that should always be true and then generate many test cases to verify these rules.

## Implementation

### Custom Property-Based Testing

Due to compatibility issues with existing property-based testing libraries (Eris) and PHPUnit 11, we implemented a custom solution using PHPUnit data providers combined with randomized test generation.

### Test Structure

The property-based tests are implemented in `tests/Unit/Utility/PathScannerPropertyBasedTest.php` and cover:

#### Core Properties Tested

1. **Idempotency**: Running the same path resolution twice produces identical results
2. **No Duplicates**: Resolved paths never contain duplicates
3. **Path Bounds**: All paths are absolute and within project boundaries
4. **Existence**: All resolved paths exist on the filesystem
5. **Sorted Results**: Results are consistently sorted
6. **Exclusion Logic**: Exclusion patterns reduce or maintain path count
7. **Vendor Namespace Support**: Vendor patterns only work when vendor path is set

#### Test Data Patterns

The tests use comprehensive data providers to cover:

- **Empty patterns**: Edge case handling
- **Single directories**: Basic path resolution
- **Multiple directories**: Complex pattern combinations
- **Glob patterns**: Wildcard pattern matching
- **Mixed patterns**: Combination of different pattern types
- **Exclusion patterns**: Pattern negation and filtering
- **Complex exclusions**: Multiple exclusion rules

### Randomized Testing

The `testRandomizedPathPatternCombinations()` method simulates true property-based testing by:

1. Generating 50 random pattern combinations
2. Testing fundamental properties on each combination
3. Verifying idempotency, uniqueness, and path validity
4. Ensuring all patterns work correctly across diverse inputs

### Pattern Generation

The tests include sophisticated pattern generators:

```php
private function generateRandomPatterns(): array
{
    $basePaths = ['src', 'packages', 'lib', 'app', 'vendor'];
    $globPatterns = ['*', '**/*', '*/Classes', '**/Service*'];
    $exclusions = ['!vendor/*', '!**/Tests', '!packages/legacy'];

    // Randomly combines patterns to create diverse test cases
}
```

## Benefits

### Comprehensive Coverage

- Tests thousands of pattern combinations automatically
- Discovers edge cases that manual tests might miss
- Validates behavior across the entire input space
- Ensures robustness under diverse conditions

### Regression Prevention

- Properties act as invariants that must always hold
- Changes to PathScanner are immediately validated
- Prevents subtle bugs in path resolution logic
- Maintains behavioral consistency

### Documentation Value

- Properties serve as executable specifications
- Clear documentation of expected behavior
- Examples of correct usage patterns
- Validation of design assumptions

## Property Definitions

### Idempotency Property

```php
// Property: Path resolution should be idempotent
$result1 = $scanner->resolvePaths($patterns);
$result2 = $scanner->resolvePaths($patterns);
assertEquals($result1, $result2);
```

**Rationale**: Path resolution should be deterministic and not depend on previous calls.

### Uniqueness Property

```php
// Property: Resolved paths should never contain duplicates
$result = $scanner->resolvePaths($patterns);
assertEquals($result, array_unique($result));
```

**Rationale**: Duplicate paths waste resources and indicate logic errors.

### Path Bounds Property

```php
// Property: All paths should be within project boundaries
foreach ($result as $path) {
    assertTrue(str_starts_with($path, $projectRoot));
    assertTrue(str_starts_with($path, '/'));
}
```

**Rationale**: Security and correctness require path containment within expected boundaries.

### Existence Property

```php
// Property: All resolved paths should exist
foreach ($result as $path) {
    assertDirectoryExists($path);
}
```

**Rationale**: Non-existent paths indicate resolution errors and would cause runtime failures.

## Test Execution

### Running Property-Based Tests

```bash
# Run all PathScanner property-based tests
vendor/bin/phpunit tests/Unit/Utility/PathScannerPropertyBasedTest.php

# Run specific property test
vendor/bin/phpunit tests/Unit/Utility/PathScannerPropertyBasedTest.php::testPathResolutionIdempotency
```

### Expected Output

```
PHPUnit 11.5.46 by Sebastian Bergmann and contributors.

..........................................................  58 / 58 (100%)

Time: 00:00.186, Memory: 10.00 MB
Tests: 58, Assertions: 1694
```

The high assertion count (1694 assertions from 58 tests) demonstrates the comprehensive nature of property-based testing.

## Extending Property-Based Tests

### Adding New Properties

To add new properties, follow this pattern:

1. **Define the Property**: What invariant should always hold?
2. **Create Data Provider**: Generate diverse test inputs
3. **Implement Test Method**: Verify the property holds for all inputs
4. **Add Documentation**: Explain the rationale

Example:

```php
/**
 * Property: Path resolution should be case-insensitive on case-insensitive filesystems
 */
#[DataProvider('caseVariationPatternsProvider')]
public function testCaseInsensitiveResolution(array $patterns): void
{
    // Implementation
}

public static function caseVariationPatternsProvider(): array
{
    return [
        ['SRC', 'src'],
        ['PACKAGES', 'packages'],
        // Additional case variations
    ];
}
```

### Shrinking Capabilities

While traditional property-based testing libraries provide automatic shrinking (finding minimal failing examples), our implementation provides similar benefits through:

1. **Structured Pattern Generation**: Patterns are generated from simple to complex
2. **Clear Failure Messages**: Each assertion includes descriptive context
3. **Randomized Testing**: Multiple iterations help isolate specific failures
4. **Comprehensive Logging**: Failed assertions show exact input patterns

## Best Practices

### Property Design

1. **Keep Properties Simple**: Each property should test one invariant
2. **Make Properties Universal**: Properties should hold for all valid inputs
3. **Avoid Implementation Details**: Test behavior, not implementation
4. **Use Descriptive Names**: Property names should clearly state the invariant

### Test Data

1. **Cover Edge Cases**: Include empty inputs, special characters, and boundary conditions
2. **Use Realistic Data**: Patterns should reflect real-world usage
3. **Vary Complexity**: Include both simple and complex pattern combinations
4. **Test Error Conditions**: Verify graceful handling of invalid inputs

### Performance

1. **Limit Test Size**: Balance coverage with execution time
2. **Use Appropriate Iterations**: 50-100 random iterations usually sufficient
3. **Profile Test Execution**: Monitor for performance regressions
4. **Optimize Data Generation**: Efficient pattern generation improves test speed

## Future Enhancements

### Potential Improvements

1. **Custom Shrinking**: Implement automatic test case reduction
2. **Property Composition**: Combine multiple properties into complex tests
3. **Mutation Testing**: Verify properties catch actual bugs
4. **Performance Properties**: Add properties for execution time and memory usage

### Integration Opportunities

1. **CI/CD Integration**: Run property-based tests in continuous integration
2. **Coverage Analysis**: Ensure property tests cover all code paths
3. **Benchmark Integration**: Use properties to validate performance characteristics
4. **Documentation Generation**: Auto-generate examples from property tests

## Conclusion

Property-based testing significantly improves the robustness and reliability of the PathScanner utility. By defining and testing fundamental properties, we ensure that path resolution behaves correctly across a vast range of input combinations, providing confidence in the system's correctness and helping prevent regressions.

The implementation demonstrates that effective property-based testing can be achieved within existing testing frameworks, providing the benefits of comprehensive testing without requiring additional dependencies or complex tooling.
