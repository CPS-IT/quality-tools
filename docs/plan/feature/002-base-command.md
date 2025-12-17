# Feature 002: Base Command

**Status:** Not Started  
**Estimated Time:** 2-3 hours  
**Layer:** MVP Core  
**Dependencies:** 001-Console Application (Not Started)

## Description

Create a single base command class that provides common functionality for all quality tool commands. This includes configuration path resolution, common option handling, process execution, and output forwarding.

## Problem Statement

All quality tool commands need to:
- Execute external processes safely and reliably
- Handle common options (verbose, quiet, config overrides)
- Resolve configuration file paths based on project structure
- Forward output from underlying tools consistently
- Provide consistent error handling and exit codes

## Goals

- Create a reusable base class that eliminates code duplication
- Implement simple configuration path resolution
- Provide safe process execution with output forwarding
- Handle common command options consistently
- Establish patterns for tool-specific commands

## Tasks

- [ ] **Base Command Class**
  - [ ] Create `src/Console/Command/BaseCommand.php` extending Symfony Command
  - [ ] Implement project root integration from QualityToolsApplication
  - [ ] Add common option definitions (verbose, quiet, config, path)
  - [ ] Provide consistent output handling methods
- [ ] **Configuration Path Resolution**
  - [ ] Add method to resolve config file paths from project root
  - [ ] Support custom config file overrides via --config option
  - [ ] Handle both relative and absolute configuration paths
  - [ ] Validate configuration file existence
- [ ] **Process Execution**
  - [ ] Implement safe external command execution using Symfony Process
  - [ ] Forward stdout/stderr from underlying tools
  - [ ] Handle process failures and timeout scenarios
  - [ ] Preserve exit codes from executed tools
- [ ] **Common Options Framework**
  - [ ] Define standard options available to all commands
  - [ ] Implement option inheritance and validation
  - [ ] Support pass-through options to underlying tools
  - [ ] Handle verbose/quiet output modes

## Success Criteria

- [ ] Base command provides 80% of functionality needed by tool commands
- [ ] Configuration file resolution works for all existing config files
- [ ] Process execution safely handles various scenarios (success, failure, timeout)
- [ ] Common options work consistently across all future command types
- [ ] Output forwarding preserves formatting and colors from underlying tools

## Technical Requirements

### BaseCommand Class

Core functionality:
- Extends Symfony\Component\Console\Command\Command
- Access to project root via application instance
- Common option definitions and parsing
- Process execution utilities
- Output formatting helpers

### Configuration Resolution

Simple path resolution:
- Calculate config file path: PROJECT_ROOT/vendor/cpsit/quality-tools/config/TOOL.EXT
- Support --config option for custom configuration files
- Validate file exists and is readable
- Return absolute path for use in tool commands

### Process Execution

Safe command execution:
- Use Symfony Process component for external commands
- Stream output in real-time for long-running operations
- Handle process failures gracefully
- Preserve original exit codes
- Respect timeout settings

### Common Options

Standard options for all commands:
- --verbose|-v: Enable verbose output
- --quiet|-q: Reduce output verbosity  
- --config: Override default configuration file
- --path: Specify custom target paths (when applicable)

## File Structure

```
src/
├── Console/Command/
│   └── BaseCommand.php               # Base class for all commands

[Future tool commands will extend BaseCommand]
```

## Implementation Plan

### Step 1: Basic Command Structure
1. Create BaseCommand class extending Symfony Command
2. Add project root access via application instance
3. Implement basic execute() method pattern

### Step 2: Configuration Resolution
1. Add resolveConfigPath() method for standard config file resolution
2. Implement --config option handling for custom overrides
3. Add validation for configuration file existence

### Step 3: Process Execution
1. Add executeProcess() method using Symfony Process
2. Implement output streaming and error handling
3. Add timeout and signal handling support

### Step 4: Common Options
1. Define common option constants and add to base command
2. Implement option parsing and validation
3. Add verbose/quiet output mode support

## Example Usage Pattern

Tool commands will follow this pattern:

```php
class RectorLintCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->resolveConfigPath('rector.php', $input->getOption('config'));
        $targetPath = $input->getOption('path') ?: $this->getProjectRoot();
        
        return $this->executeProcess([
            $this->getProjectRoot() . '/vendor/bin/rector',
            '-c', $configPath,
            '--dry-run',
            $targetPath
        ], $input, $output);
    }
}
```

## Configuration Schema

Default configuration path calculation:
```
PROJECT_ROOT/vendor/cpsit/quality-tools/config/{tool}.{ext}
```

Examples:
- Rector: `config/rector.php`
- PHPStan: `config/phpstan.neon`  
- PHP CS Fixer: `config/php-cs-fixer.php`
- Fractor: `config/fractor.php`
- TypoScript Lint: `config/typoscript-lint.yml`

Override support:
```bash
qt lint:rector --config=/custom/path/rector.php
qt lint:phpstan --path=./custom/src --verbose
```

## Error Handling

Common error scenarios:
- Configuration file not found: Clear path to expected location
- Tool binary not available: Guide to installation or composer update
- Process execution failure: Forward original error with context
- Permission issues: Clear message about file system access

## Performance Considerations

- Efficient process execution with proper resource management
- Minimal overhead for simple command forwarding
- No unnecessary file system operations during path resolution

## Security Considerations

- Safe external process execution with proper argument escaping
- Validation of configuration file paths to prevent traversal
- Secure handling of user-provided options and paths

## Testing Strategy

- Unit tests for configuration path resolution
- Mock testing for process execution scenarios  
- Integration tests with real tool binaries
- Error handling and edge case testing
- Option parsing and validation testing

## Dependencies

- `symfony/process`: ^6.0|^7.0 - Safe external process execution
- `symfony/console`: ^6.0|^7.0 - Command framework and options

## Risk Assessment

**Medium Risk:**
- Process execution across different operating systems
- Handling various tool output formats and error conditions

**Mitigation:**
- Comprehensive testing on different platforms
- Robust error handling and timeout management
- Clear separation between safe and unsafe operations

## Future Enhancements

- Advanced output formatting with colors and progress bars
- Process result caching for repeated operations
- Parallel execution support for batch operations
- Custom timeout configuration per tool

## Notes

- Keep configuration resolution simple - avoid complex logic
- Design for easy extension by tool-specific commands
- Ensure process execution is isolated and safe
- Plan for future batch operation support in the architecture