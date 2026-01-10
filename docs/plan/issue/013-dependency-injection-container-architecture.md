# Issue 013: Dependency Injection Container for Architecture Improvements

**Status:** Work in Progress
**Priority:** Medium
**Effort:** Very High (1w+)
**Impact:** Medium

## Description

The current architecture lacks a proper dependency injection container, leading to tight coupling between classes, challenging testing scenarios, and reduced extensibility for future enhancements.

## Root Cause

The codebase relies on direct instantiation and static dependencies:
- Configuration objects are tightly coupled to command classes
- Hard-coded service instantiation throughout the codebase
- Limited ability to mock dependencies for testing
- No centralized service configuration

## Error Details

**Error Message:**
```
Tight coupling between classes without dependency injection container
```

**Location:** Throughout the codebase, particularly in command classes and utility services
**Trigger:** Attempting to test components in isolation or extend functionality

## Impact Analysis

**Affected Components:**
- Command classes and their dependencies
- Configuration management system
- Utility services (PathScanner, etc.)
- Testing framework and mock capabilities

**User Impact:**
- Limited extensibility for custom configurations
- Reduced plugin/extension capabilities
- Harder to troubleshoot complex configuration scenarios

**Technical Impact:**
- Challenging unit testing with proper isolation
- Tight coupling reduces code maintainability
- Limited ability to swap implementations for different environments

## Possible Solutions

### Solution 1: Lightweight DI Container Implementation
- **Description:** Implement a simple dependency injection container focusing on core services
- **Effort:** High
- **Impact:** High effectiveness for architecture improvement
- **Pros:** Custom solution, minimal external dependencies, focused on specific needs
- **Cons:** Requires significant implementation effort, reinventing existing solutions

### Solution 2: Symfony DI Component Integration
- **Description:** Use Symfony's mature DI component for robust dependency management
- **Effort:** Very High
- **Impact:** High effectiveness, industry-standard solution
- **Pros:** Mature, well-tested, extensive features, good documentation
- **Cons:** Additional dependency, learning curve, may be overkill for package size

## Recommended Solution

**Choice:** Lightweight DI Container Implementation with Symfony DI patterns

Start with a simple implementation following Symfony patterns, allowing future migration if needed.

**Implementation Steps:**
1. Design service container interface and basic implementation
2. Define service definitions for core components (PathScanner, Configuration, etc.)
3. Refactor command classes to use dependency injection
4. Implement service factories for complex object creation
5. Update testing framework to work with injectable dependencies
6. Create configuration system for service definitions
7. Add documentation for extending the container

## Validation Plan

- [ ] All services can be properly injected and resolved
- [ ] Circular dependency detection works correctly
- [ ] Testing with mocked services functions properly
- [ ] Configuration loading integrates with service container
- [ ] Performance impact is minimal
- [ ] Extensibility for future services is demonstrated

## Dependencies

- Consider PSR-11 Container Interface for standard compliance
- May require updates to existing testing patterns

## Workarounds

Continue with current architecture while ensuring new components are designed with DI in mind:
1. Use factory patterns for complex object creation
2. Pass dependencies as constructor parameters where possible
3. Avoid static method usage for testability

## Related Issues

- Command template pattern (issue 010) will benefit from consistent dependency injection
- Configuration validation may require service-based architecture
- Testing improvements will benefit from better dependency isolation
