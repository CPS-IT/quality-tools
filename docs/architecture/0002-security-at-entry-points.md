# [ADR-0002] Security at Entry Points

## Status

Accepted

## Context

When implementing secure path resolution across the configuration system, we needed to decide where to apply security validation:

- Security validation could be applied at every level of the system
- Multiple components handle file paths: commands, validators, loaders, services
- Redundant validation would increase code complexity and performance overhead
- Inconsistent validation could create security gaps

## Decision

We will apply security validation at system entry points rather than throughout the codebase.

Security validation will be enforced at:
- ConfigurationDiscovery when loading external configuration files
- BaseCommand when processing user-provided --config options
- Initial file access points in FilesystemService

Internal components will trust that paths have been pre-validated.

## Consequences

### Positive Consequences
- Reduced code complexity with single validation points
- Consistent security enforcement across all paths
- Better performance with validation only at boundaries
- Clear security perimeter for the system
- Easier to audit and maintain security controls

### Negative Consequences
- Internal components must trust pre-validated paths
- Risk if new entry points are added without validation
- Requires careful documentation of security boundaries

### Neutral Consequences
- Security becomes a boundary concern rather than distributed
- Validators and internal services don't perform their own path validation
- Clear separation between external input handling and internal processing

## Alternatives Considered

### Alternative 1: Validation at Every Level
- Every component validates paths independently
- Why not chosen: Redundant validation, performance overhead, complex to maintain

### Alternative 2: Validation Only in Services
- Only service layer performs validation
- Why not chosen: Would miss command-line entry points, inconsistent coverage

### Alternative 3: Aspect-Oriented Security
- Use AOP to inject security validation
- Why not chosen: Adds complexity, harder to debug, not native to PHP

## Implementation Notes

- ConfigurationDiscovery validates in `loadToolConfigurationFile()`
- BaseCommand validates in `resolveConfigPath()`
- Tool validators receive pre-validated paths
- FilesystemService provides `validateConfigurationPath()` as the central validation method

## References

- Issue 022: Configuration File Replacement Schema Validation Bug
- Phase 2, Step 5: Secure Path Resolution Integration
- Related: ADR-0001 Context-Aware Security Validation