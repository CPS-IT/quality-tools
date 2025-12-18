# Feature: Unified Lint Command

**Status:** Not Started  
**Estimated Time:** 6-8 hours  
**Layer:** MVP  
**Dependencies:** unified-configuration-system (Not Started), unified-arguments-options (Not Started)

## Description

Implement a `qt lint` command that executes all linting tools (PHPStan, TypoScript Lint, EditorConfig CLI) in a single, coordinated operation. This provides a comprehensive code quality check with unified output and reporting.

## Problem Statement

Currently, running all linting tools requires multiple separate commands:

- Each tool has different command-line syntax and options
- No unified output or summary across tools
- Difficult to get overall project linting status
- Inconsistent error handling and exit codes
- Time-consuming to run tools individually

## Goals

- Single command runs all applicable linting tools
- Unified output format and progress reporting
- Consistent error handling and exit codes
- Parallel execution for improved performance
- Configurable tool inclusion/exclusion

## Tasks

- [ ] Command Infrastructure
  - [ ] Design `qt lint` command structure and options
  - [ ] Implement unified argument parsing and validation
  - [ ] Create tool orchestration and execution engine
  - [ ] Add parallel execution capabilities with proper synchronization
- [ ] Tool Integration
  - [ ] Integrate PHPStan static analysis
  - [ ] Add TypoScript Lint for TypoScript files
  - [ ] Include EditorConfig CLI validation
  - [ ] Support for additional linting tools in the future
- [ ] Output and Reporting
  - [ ] Create unified progress reporting during execution
  - [ ] Implement consolidated results summary
  - [ ] Add tool-specific error aggregation and display
  - [ ] Generate combined exit codes based on all tool results
- [ ] Configuration and Customization
  - [ ] Support tool-specific configuration overrides
  - [ ] Add option to include/exclude specific tools
  - [ ] Implement fail-fast vs complete execution modes
  - [ ] Create quiet and verbose output options

## Success Criteria

- [ ] `qt lint` executes all relevant linting tools
- [ ] Results are presented in unified, readable format
- [ ] Command respects unified configuration and arguments
- [ ] Parallel execution improves overall performance
- [ ] Exit code reflects overall linting success/failure status

## Technical Requirements

### Command Interface

```bash
# Basic usage
qt lint

# With options
qt lint --path src/ --verbose --fail-fast
qt lint --exclude-tool phpstan --dry-run
qt lint --format json --output results.json
```

### Tool Execution Strategy

- Parallel execution where safe (non-interfering tools)
- Sequential execution for tools that modify files or state
- Proper resource management and cleanup
- Timeout handling for long-running tools

### Output Format

```
Quality Tools Lint Summary
==========================

✓ PHPStan (Level 6)     - 0 errors, 2 warnings
✗ TypoScript Lint       - 3 errors, 1 warning  
✓ EditorConfig CLI      - 0 errors

Total: 3 errors, 3 warnings in 2.4s
```

## Implementation Plan

### Phase 1: Basic Command Structure

1. Implement `qt lint` command parser and validation
2. Create basic tool orchestration framework
3. Add sequential execution of all linting tools
4. Implement unified output formatting

### Phase 2: Enhanced Execution

1. Add parallel execution capabilities
2. Implement tool-specific configuration handling
3. Add progress reporting and status updates
4. Create comprehensive error handling

### Phase 3: Advanced Features

1. Add tool inclusion/exclusion options
2. Implement multiple output formats
3. Add performance optimization and caching
4. Create extensive testing and validation

## Configuration Schema

```yaml
commands:
  lint:
    # Default tools to run
    tools:
      - phpstan
      - typoscript-lint
      - editorconfig-cli
    
    # Execution options
    execution:
      parallel: true
      timeout: 300  # seconds
      fail_fast: false
    
    # Output options
    output:
      progress: true
      summary: true
      format: "text"  # text, json, xml
    
    # Tool-specific overrides
    tool_options:
      phpstan:
        level: 6
        memory_limit: "1G"
      typoscript-lint:
        fail_on_warnings: false
```

## Performance Considerations

- Parallel execution of independent tools
- Efficient resource sharing and cleanup
- Memory usage optimization for large codebases
- Caching of tool configurations and results
- Smart tool ordering based on execution time

## Testing Strategy

- Unit tests for command parsing and validation
- Integration tests with all supported linting tools
- Parallel execution safety and correctness tests
- Performance tests comparing sequential vs parallel execution
- Error handling and edge case scenario tests

## Backward Compatibility

- Individual tool commands remain functional
- Tool-specific configurations are preserved
- Existing CI/CD scripts continue working
- `qt lint` supplements rather than replaces individual tools

## Risk Assessment

**Low:**
- Read-only linting operations have minimal risk
- Well-established tools with predictable behavior
- Unified interface simplifies rather than complicates usage

**Mitigation:**
- Comprehensive testing with various project structures
- Fallback to sequential execution if parallel fails
- Clear error reporting for tool-specific issues
- Extensive validation of tool configurations

## Future Enhancements

- Custom linting tool plugins
- Incremental linting for changed files only
- Integration with IDE linting workflows
- Linting result caching and optimization
- Team-based linting standards and enforcement

## Notes

- Focus on most commonly used linting tools first
- Ensure tool output is properly captured and formatted
- Consider memory and performance implications of parallel execution
- Plan for easy addition of new linting tools
- Maintain clear separation between linting (read-only) and fixing operations
