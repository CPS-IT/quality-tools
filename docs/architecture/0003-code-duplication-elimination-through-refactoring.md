# [ADR-0003] Code Duplication Elimination Through Refactoring

## Status

Accepted

## Context

During the implementation of secure path resolution, we identified significant code duplication in ConfigurationDiscovery:

- `loadPhpFile()` and `loadNeonFile()` methods contained nearly identical logic
- Both methods performed the same validation, error handling, and data structure creation
- Only the file extension check differed between them
- Duplicate code increases maintenance burden and risk of divergent behavior

## Decision

We will eliminate code duplication by extracting common logic into a shared method.

The refactoring will:
- Create a new `loadToolConfigurationFile()` method with common logic
- Keep `loadPhpFile()` and `loadNeonFile()` as thin wrappers for compatibility
- Centralize validation, error handling, and configuration structure creation

## Consequences

### Positive Consequences
- Single source of truth for configuration loading logic
- Reduced maintenance burden with less code to update
- Consistent behavior guaranteed between PHP and Neon loading
- Easier to add support for new file types in the future
- Improved code readability and understanding

### Negative Consequences
- Additional method in the call stack (minimal performance impact)
- Slightly more complex to trace execution flow during debugging

### Neutral Consequences
- Public API remains unchanged (backward compatible)
- Internal implementation detail not visible to consumers

## Alternatives Considered

### Alternative 1: Keep Duplicate Code
- Leave the methods as-is with duplication
- Why not chosen: Violates DRY principle, increases maintenance burden

### Alternative 2: Merge Into Single Method
- Replace both methods with a single method accepting file type
- Why not chosen: Would break backward compatibility, changes public API

### Alternative 3: Template Method Pattern
- Use inheritance with template method pattern
- Why not chosen: Over-engineering for this simple case

## Implementation Notes

- Implemented in `ConfigurationDiscovery`
- New private method: `loadToolConfigurationFile(string $path): array`
- Original methods delegate to shared implementation
- Maintains all existing error handling and validation

## References

- Issue 022: Configuration File Replacement Schema Validation Bug
- Phase 2, Step 5: Secure Path Resolution Integration
- DRY (Don't Repeat Yourself) Principle
