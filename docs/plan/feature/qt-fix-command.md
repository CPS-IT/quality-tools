# Feature: Unified Fix Command

**Status:** Not Started  
**Estimated Time:** 8-10 hours  
**Layer:** MVP  
**Dependencies:** unified-configuration-system (Not Started), unified-arguments-options (Not Started), qt-lint-command (Not Started)

## Description

Implement a `qt fix` command that executes all code fixing tools (Rector, Fractor, PHP CS Fixer, EditorConfig CLI) in a single, coordinated operation. This provides comprehensive code quality fixes with proper sequencing and conflict resolution.

## Problem Statement

Currently, running all fixing tools requires multiple separate commands with careful sequencing:

- Tools may interfere with each other if run simultaneously
- No coordination between different types of fixes
- Risk of conflicts between tool modifications
- Difficult to ensure proper execution order
- No unified reporting of all changes made

## Goals

- Single command runs all applicable fixing tools in proper sequence
- Intelligent tool ordering to prevent conflicts
- Unified dry-run mode showing all proposed changes
- Coordinated backup and rollback capabilities
- Comprehensive reporting of all modifications

## Tasks

- [ ] Command Infrastructure
  - [ ] Design `qt fix` command structure and options
  - [ ] Implement tool execution sequencing and dependencies
  - [ ] Create conflict detection and resolution mechanisms
  - [ ] Add unified dry-run and preview capabilities
- [ ] Tool Integration and Sequencing
  - [ ] Integrate Rector for PHP code modernization
  - [ ] Add Fractor for TypoScript modernization
  - [ ] Include PHP CS Fixer for code style fixes
  - [ ] Add EditorConfig CLI for formatting fixes
  - [ ] Implement proper execution order (Rector → PHP CS Fixer → EditorConfig)
- [ ] Safety and Recovery
  - [ ] Create automatic backup before fixes
  - [ ] Implement rollback capability for failed fixes
  - [ ] Add validation of fixes before committing
  - [ ] Create fix verification and testing integration
- [ ] Reporting and Output
  - [ ] Generate comprehensive change summary
  - [ ] Show before/after comparisons for significant changes
  - [ ] Create unified progress reporting during execution
  - [ ] Add detailed logging of all tool operations

## Success Criteria

- [ ] `qt fix` executes all fixing tools in optimal sequence
- [ ] No conflicts or interference between tool operations
- [ ] Dry-run mode accurately previews all proposed changes
- [ ] Automatic backup and rollback prevents data loss
- [ ] Comprehensive reporting shows all modifications made

## Technical Requirements

### Command Interface

```bash
# Basic usage
qt fix

# With options
qt fix --dry-run --verbose
qt fix --exclude-tool rector --backup
qt fix --path src/ --verify-fixes
qt fix --interactive --confirm-changes
```

### Tool Execution Sequence

1. **Rector** - PHP code modernization (may change structure significantly)
2. **Fractor** - TypoScript modernization (may change TypoScript structure)
3. **PHP CS Fixer** - Code style fixes (formatting only, safe after structural changes)
4. **EditorConfig CLI** - Final formatting cleanup (safe after all other changes)

### Safety Mechanisms

- Automatic Git stash or backup before fixes
- Validation that changes don't break syntax
- Optional test execution after fixes
- Rollback capability if issues detected

## Implementation Plan

### Phase 1: Basic Fix Orchestration

1. Implement `qt fix` command parser and validation
2. Create tool execution sequencing framework
3. Add basic sequential execution of fixing tools
4. Implement unified dry-run capabilities

### Phase 2: Safety and Conflict Resolution

1. Add automatic backup and rollback mechanisms
2. Implement conflict detection between tools
3. Create fix validation and verification
4. Add comprehensive error handling and recovery

### Phase 3: Advanced Features

1. Implement interactive fix confirmation
2. Add selective tool execution and filtering
3. Create detailed change reporting and analysis
4. Add integration with testing and validation workflows

## Configuration Schema

```yaml
commands:
  fix:
    # Tool execution sequence
    tools:
      - name: rector
        order: 1
        enabled: true
      - name: fractor
        order: 2
        enabled: true
      - name: php-cs-fixer
        order: 3
        enabled: true
      - name: editorconfig-cli
        order: 4
        enabled: true
    
    # Safety options
    safety:
      auto_backup: true
      verify_syntax: true
      run_tests: false  # Optional test execution after fixes
      rollback_on_failure: true
    
    # Execution options
    execution:
      interactive: false
      confirm_changes: false
      max_iterations: 3  # Re-run sequence if tools make changes
    
    # Output options
    output:
      show_diffs: true
      detailed_summary: true
      progress_reporting: true
```

## File Structure

```
.quality-tools/
├── backups/
│   ├── 2023-12-18T10-30-00/   # Timestamped backups
│   └── latest/                # Latest backup
├── logs/
│   └── fix-2023-12-18.log    # Detailed execution logs
└── cache/
    └── tool-states.json      # Tool execution state tracking
```

## Performance Considerations

- Sequential execution prevents conflicts but may be slower
- Efficient change detection to avoid unnecessary tool runs
- Smart caching of tool configurations and states
- Optimization of file I/O and temporary file usage
- Memory management for large codebases

## Security Considerations

- Secure backup and rollback mechanisms
- Validation of tool outputs before applying changes
- Protection against malicious tool configurations
- Safe handling of temporary files and directories
- Proper cleanup of backup and temporary data

## Testing Strategy

- Unit tests for tool sequencing and conflict detection
- Integration tests with all fixing tools
- Safety mechanism tests (backup, rollback, verification)
- Performance tests with large codebases
- End-to-end workflow tests with real project scenarios

## Backward Compatibility

- Individual tool commands remain functional
- Existing tool configurations are preserved
- `qt fix` supplements rather than replaces individual tools
- No changes to underlying tool behavior or output

## Risk Assessment

**Medium:**
- Complex tool sequencing and conflict resolution
- Risk of data loss if safety mechanisms fail
- Potential for cascading failures across multiple tools

**Mitigation:**
- Comprehensive backup and rollback mechanisms
- Extensive testing with various project structures
- Conservative default settings with opt-in advanced features
- Clear error reporting and recovery guidance
- Fail-safe defaults that prefer safety over functionality

## Future Enhancements

- Machine learning-based fix optimization and sequencing
- Integration with code review workflows
- Custom fix tool plugins and extensions
- Team-based fix policies and approval workflows
- Fix impact analysis and risk assessment

## Notes

- Safety should be the top priority - prefer cautious approach over speed
- Tool sequencing is critical - structural changes before formatting
- Comprehensive testing required due to complexity of interactions
- Consider performance implications of sequential execution
- Plan for easy debugging and troubleshooting of fix sequences
