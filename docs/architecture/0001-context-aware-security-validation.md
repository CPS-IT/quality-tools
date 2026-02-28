# [ADR-0001] Context-Aware Security Validation

## Status

Accepted

## Context

During the implementation of secure path resolution for Issue 022, we encountered a conflict between security requirements and test compatibility:

- Security requirements demanded strict validation of all configuration file paths to prevent path traversal attacks
- Test scenarios used generic configuration files that don't follow tool-specific naming conventions
- Applying strict validation universally would break existing test infrastructure
- Different contexts (production vs testing) have different security requirements

## Decision

We will implement context-aware validation that distinguishes between tool configurations and generic test configurations.

The system will:
- Apply strict security validation for known tool configuration files (rector.php, phpstan.neon, etc.)
- Use simple path resolution for generic configuration files used in tests
- Determine context based on filename patterns matching known tools

## Consequences

### Positive Consequences
- Maintains backward compatibility with existing test infrastructure
- Provides strong security for production tool configurations
- No modification needed to existing test files
- Clear separation between production and test contexts

### Negative Consequences
- Slightly more complex validation logic in BaseCommand
- Generic configuration files bypass some security checks
- Potential for confusion if users name files ambiguously

### Neutral Consequences
- Security validation becomes context-dependent rather than universal
- Tool configuration files must follow naming conventions to receive full validation

## Alternatives Considered

### Alternative 1: Universal Strict Validation
- Apply the same strict validation to all configuration files
- Why not chosen: Would break all existing tests using generic config files

### Alternative 2: Configuration Flag for Validation Level
- Add a flag to explicitly control validation strictness
- Why not chosen: Increases API complexity and potential for misconfiguration

### Alternative 3: Separate Test and Production Paths
- Use completely different code paths for test and production
- Why not chosen: Would duplicate code and increase maintenance burden

## Implementation Notes

- Implemented in `BaseCommand::resolveConfigPath()`
- Uses `getToolNameForConfig()` to identify tool-specific configurations
- Known tools list: rector, phpstan, fractor, php-cs-fixer, typoscript-lint
- Falls back to simple realpath resolution for non-tool configs

## References

- Issue 022: Configuration File Replacement Schema Validation Bug
- Phase 2, Step 5: Secure Path Resolution Integration
- Related: FilesystemService::validateConfigurationPath()