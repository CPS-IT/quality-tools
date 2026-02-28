# [ADR-0004] Inheritance-Based Security Propagation

## Status

Accepted

## Context

When implementing secure path resolution for tool commands, we needed to ensure all commands have consistent security validation:

- Multiple tool command classes exist (RectorLintCommand, PhpCsFixerFixCommand, etc.)
- Each command handles configuration file paths
- Modifying each command individually would be error-prone and repetitive
- All commands already inherit from BaseCommand or AbstractToolCommand

## Decision

We will leverage the existing inheritance hierarchy to propagate security validation automatically.

The approach:
- Implement security validation once in BaseCommand::resolveConfigPath()
- All tool commands inherit this security through existing class hierarchy
- No modifications needed to individual command classes
- AbstractToolCommand (which extends BaseCommand) also inherits security

## Consequences

### Positive Consequences
- Zero code changes required in tool command classes
- Automatic security for all current and future commands
- Single point of maintenance for security logic
- Consistent security behavior across all tools
- Reduced risk of forgetting to secure new commands

### Negative Consequences
- Commands are tightly coupled to BaseCommand security implementation
- Harder to provide tool-specific security exceptions if needed

### Neutral Consequences
- Security becomes an inherited characteristic rather than explicit
- All commands must use resolveConfigPath() to benefit from security

## Alternatives Considered

### Alternative 1: Modify Each Command Individually
- Add security validation to each tool command class
- Why not chosen: Repetitive, error-prone, high maintenance burden

### Alternative 2: Security Decorator Pattern
- Wrap commands with security decorators
- Why not chosen: Would require changes to command registration and instantiation

### Alternative 3: Aspect-Oriented Approach
- Use AOP to inject security concerns
- Why not chosen: Adds complexity, not standard in PHP ecosystem

## Implementation Notes

- Security implemented in BaseCommand::resolveConfigPath()
- All tool commands verified to extend BaseCommand or AbstractToolCommand
- No changes needed to: RectorLintCommand, PhpCsFixerLintCommand, FractorLintCommand, etc.
- Test verification: All command tests pass without modification

## References

- Issue 022: Configuration File Replacement Schema Validation Bug
- Phase 2, Step 5: Secure Path Resolution Integration
- Related: ADR-0002 Security at Entry Points