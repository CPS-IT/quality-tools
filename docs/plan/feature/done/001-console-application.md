# Feature 001: Console Application

**Status:** Completed\
**Estimated Time:** 2-3 hours\
**Layer:** MVP Core\
**Dependencies:** None

## Description

Create the main Symfony Console application that serves as the entry point for the 'qt' command. This includes the application class, command registration, and basic project root detection via composer.json traversal.

## Problem Statement

Users need a simple entry point to access quality tools without remembering complex paths and configurations. The application must:
- Provide a clean 'qt' command interface
- Automatically detect the TYPO3 project root
- Register and organize all available tool commands
- Display helpful information (version, help, available commands)

## Goals

- Create working 'qt' command that displays help and version
- Implement basic project root detection via composer.json traversal
- Set up command registration system for tool commands
- Establish foundation for all tool commands

## Tasks

- [x] **Create Console Application**
  - [x] Create `src/Console/QualityToolsApplication.php` extending Symfony Console Application
  - [x] Configure application name, version, and description
  - [x] Implement automatic command discovery and registration
- [x] **Project Root Detection**
  - [x] Add method to find nearest composer.json file traversing up directory tree
  - [x] Validate that found project contains TYPO3 dependencies
  - [x] Provide fallback for when project root cannot be detected
- [x] **Executable Setup**
  - [x] Create `bin/qt` executable script
  - [x] Update `composer.json` with bin configuration
  - [x] Set up PSR-4 autoloading for src/ directory
- [x] **Basic Error Handling**
  - [x] Handle cases where composer.json is not found
  - [x] Provide clear error messages for common issues
  - [x] Ensure proper exit codes

## Success Criteria

- [x] `qt` command displays application help and version information
- [x] Application correctly detects TYPO3 project root in typical setups
- [x] Commands are properly registered and discoverable via `qt list`
- [x] Error messages are clear and actionable when project detection fails

## Technical Requirements

### QualityToolsApplication Class

Core functionality:
- Extends Symfony\Component\Console\Application
- Application name: "CPSIT Quality Tools"
- Version from composer.json or hardcoded
- Description of package purpose

### Project Root Detection

Simple traversal approach:
- Start from current directory
- Walk up directory tree looking for composer.json
- Verify composer.json contains TYPO3-related dependencies
- Return absolute path to project root
- Throw exception if not found after reasonable traversal

### Command Registration

Automatic discovery:
- Scan Command/ directory for command classes
- Register each command with appropriate name
- Use class-based command names (e.g., RectorLintCommand -> rector:lint)

## File Structure

```
src/
├── Console/
│   ├── QualityToolsApplication.php    # Main application class
│   └── Command/
       └── [tool commands created in other features]

bin/
└── qt                                 # Executable entry point

composer.json                         # Updated with bin and autoload config
```

## Implementation Plan

### Step 1: Basic Application Setup
1. Create QualityToolsApplication class with basic Symfony Console setup
2. Implement application metadata (name, version, description)
3. Create bin/qt executable with basic bootstrap

### Step 2: Project Detection
1. Add findProjectRoot() method using directory traversal
2. Add basic validation for TYPO3 project structure
3. Add error handling for project detection failures

### Step 3: Command Registration
1. Implement automatic command discovery from Command/ directory
2. Set up command name mapping from class names
3. Test command registration and help output

## Configuration

Default behavior:
- Start project detection from current working directory
- Look for composer.json files in parent directories (max 10 levels up)
- Validate composer.json contains 'typo3/cms-core' or similar dependency
- Use found directory as project root for all subsequent operations

Override support:
- Environment variable QT_PROJECT_ROOT for manual override
- Command line option --project-root for per-command override

## Error Scenarios

Common failure modes:
- composer.json not found: Clear message about running from within TYPO3 project
- Invalid project structure: Message about TYPO3 dependencies not found
- Permission issues: Clear message about file system permissions

## Performance Considerations

- Efficient directory traversal with reasonable limits
- Cache project root detection result during single command execution
- Lazy loading of command classes until needed

## Security Considerations

- Validate file system paths to prevent traversal attacks
- Safe handling of composer.json parsing
- Proper escaping of paths used in error messages

## Testing Strategy

- Unit tests for project root detection logic
- Integration tests with sample project structures
- Error handling tests for various failure scenarios
- Command registration and discovery testing

## Dependencies

- `symfony/console`: ^6.0|^7.0 - Console application framework
- `symfony/process`: ^6.0|^7.0 - For future command execution needs

## Risk Assessment

**Low Risk:**
- Simple directory traversal and JSON parsing
- Well-established Symfony Console patterns
- Minimal external dependencies

**Mitigation:**
- Comprehensive testing of edge cases
- Clear error messages for troubleshooting
- Fallback options for problematic environments

## Future Enhancements

- Configuration file support for project-specific settings
- Plugin system for third-party tool integration
- Interactive project setup wizard
- Caching of project detection results

## Notes

- Keep project detection simple - avoid complex validation logic
- Focus on common TYPO3 project structures, handle edge cases later
- Ensure error messages guide users toward solutions
- Design command registration to be easily extensible
