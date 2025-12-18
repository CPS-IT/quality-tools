# Feature 003: Tool Commands

**Status:** Completed
**Estimated Time:** 4-6 hours
**Layer:** MVP Core
**Dependencies:** 001-Console Application, 002-Base Command

## Description

Implement all individual tool commands that transform complex command paths into simple 'qt' shortcuts. Each command extends BaseCommand and provides minimal logic to execute the appropriate underlying tool with correct configuration.

## Problem Statement

Users currently need to remember and type complex commands like:
```bash
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run
```

Instead, they should be able to use simple commands like:
```bash
qt lint:rector
```

## Goals

- Transform all existing tool commands into simple 'qt' equivalents
- Maintain 100% compatibility with existing tool functionality
- Provide both 'lint' (dry-run) and 'fix' (apply changes) modes where applicable
- Keep command implementation minimal and focused
- Ensure consistent behavior across all tool commands

## Tasks

- [x] **Rector Commands**
  - [x] Create `RectorLintCommand.php` for `qt lint:rector` (dry-run mode)
  - [x] Create `RectorFixCommand.php` for `qt fix:rector` (apply changes)
  - [x] Support custom path specification and pass-through options
- [x] **Fractor Commands**
  - [x] Create `FractorLintCommand.php` for `qt lint:fractor` (dry-run mode)
  - [x] Create `FractorFixCommand.php` for `qt fix:fractor` (apply changes)
  - [x] Handle TypoScript-specific configuration and paths
- [x] **PHPStan Command**
  - [x] Create `PhpStanCommand.php` for `qt lint:phpstan` (analysis only)
  - [x] Support custom analysis level and path specification
  - [x] Handle memory limit and performance options
- [x] **PHP CS Fixer Commands**
  - [x] Create `PhpCsFixerLintCommand.php` for `qt lint:php-cs-fixer` (dry-run)
  - [x] Create `PhpCsFixerFixCommand.php` for `qt fix:php-cs-fixer` (apply fixes)
  - [x] Support custom rule sets and path targeting
- [x] **TypoScript Lint Command**
  - [x] Create `TypoScriptLintCommand.php` for `qt lint:typoscript`
  - [x] Handle TypoScript file discovery and validation
  - [x] Support custom path specification for TypoScript files
- [x] **Composer Commands**
  - [x] Create `ComposerLintCommand.php` for `qt lint:composer` (validate)
  - [x] Create `ComposerFixCommand.php` for `qt fix:composer` (normalize)
  - [x] Handle composer.json validation and normalization

## Success Criteria

- [x] All 10 tool commands are implemented and working
- [x] Commands properly forward options to underlying tools
- [x] Configuration file resolution works correctly for each tool
- [x] Process execution handles success, failure, and timeout scenarios
- [x] Output formatting preserves tool-specific formatting and colors
- [x] Exit codes are properly forwarded from underlying tools

## Technical Requirements

### Command Structure

Each tool command follows this minimal pattern:
```php
class ToolCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('category:tool')
            ->setDescription('Tool description')
            ->addOption('tool-specific-option', null, InputOption::VALUE_OPTIONAL, 'Description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->resolveConfigPath('config-file.ext', $input->getOption('config'));
        $targetPath = $input->getOption('path') ?: $this->getProjectRoot();

        return $this->executeProcess([
            $this->getProjectRoot() . '/vendor/bin/tool-binary',
            '--config=' . $configPath,
            $targetPath
        ], $input, $output);
    }
}
```

### Tool-Specific Requirements

**Rector Commands:**
- Binary: `vendor/bin/rector`
- Config: `config/rector.php`
- Lint: Add `--dry-run` flag
- Fix: No additional flags

**Fractor Commands:**
- Binary: `vendor/bin/fractor`
- Config: `config/fractor.php`
- Command: `process` subcommand
- Lint: Add `--dry-run` flag
- Fix: No additional flags

**PHPStan Command:**
- Binary: `vendor/bin/phpstan`
- Config: `config/phpstan.neon`
- Command: `analyse` subcommand
- Options: Support `--level`, `--memory-limit`

**PHP CS Fixer Commands:**
- Binary: `vendor/bin/php-cs-fixer`
- Config: `config/php-cs-fixer.php`
- Command: `fix` subcommand
- Lint: Add `--dry-run` flag
- Fix: No additional flags

**TypoScript Lint Command:**
- Binary: `vendor/bin/typoscript-lint`
- Config: `config/typoscript-lint.yml`
- Default paths: Auto-discover TypoScript files

**Composer Commands:**
- Binary: `vendor/bin/composer-normalize`
- No config file required
- Lint: Add `--dry-run` flag
- Fix: No additional flags

## File Structure

```
src/Console/Command/
├── BaseCommand.php                   # Base functionality
├── RectorLintCommand.php            # qt lint:rector
├── RectorFixCommand.php             # qt fix:rector
├── FractorLintCommand.php           # qt lint:fractor
├── FractorFixCommand.php            # qt fix:fractor
├── PhpStanCommand.php               # qt lint:phpstan
├── PhpCsFixerLintCommand.php        # qt lint:php-cs-fixer
├── PhpCsFixerFixCommand.php         # qt fix:php-cs-fixer
├── TypoScriptLintCommand.php        # qt lint:typoscript
├── ComposerLintCommand.php          # qt lint:composer
└── ComposerFixCommand.php           # qt fix:composer
```

## Implementation Plan

### Step 1: Rector Commands (1 hour)
1. Implement RectorLintCommand with dry-run mode
2. Implement RectorFixCommand for applying changes
3. Test with existing rector configuration

### Step 2: PHPStan Command (30 minutes)
1. Implement PhpStanCommand for static analysis
2. Add support for common PHPStan options
3. Test with existing phpstan configuration

### Step 3: PHP CS Fixer Commands (1 hour)
1. Implement PhpCsFixerLintCommand with dry-run
2. Implement PhpCsFixerFixCommand for applying fixes
3. Test with existing php-cs-fixer configuration

### Step 4: Fractor Commands (1 hour)
1. Implement FractorLintCommand with dry-run mode
2. Implement FractorFixCommand for applying changes
3. Handle TypoScript-specific requirements

### Step 5: Remaining Commands (1-2 hours)
1. Implement TypoScriptLintCommand
2. Implement ComposerLintCommand and ComposerFixCommand
3. Test all commands with real project scenarios

## Command Mapping

| Current Command | New Command | Tool Binary |
|-----------------|-------------|-------------|
| `app/vendor/bin/rector -c vendor/cpsit/quality-tools/config/rector.php --dry-run` | `qt lint:rector` | rector |
| `app/vendor/bin/rector -c vendor/cpsit/quality-tools/config/rector.php` | `qt fix:rector` | rector |
| `app/vendor/bin/fractor process --dry-run -c vendor/cpsit/quality-tools/config/fractor.php` | `qt lint:fractor` | fractor |
| `app/vendor/bin/fractor process -c vendor/cpsit/quality-tools/config/fractor.php` | `qt fix:fractor` | fractor |
| `app/vendor/bin/phpstan analyse -c vendor/cpsit/quality-tools/config/phpstan.neon` | `qt lint:phpstan` | phpstan |
| `app/vendor/bin/php-cs-fixer fix --dry-run --config=vendor/cpsit/quality-tools/config/php-cs-fixer.php` | `qt lint:php-cs-fixer` | php-cs-fixer |
| `app/vendor/bin/php-cs-fixer fix --config=vendor/cpsit/quality-tools/config/php-cs-fixer.php` | `qt fix:php-cs-fixer` | php-cs-fixer |
| `vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml` | `qt lint:typoscript` | typoscript-lint |
| `vendor/bin/composer-normalize --dry-run` | `qt lint:composer` | composer-normalize |
| `vendor/bin/composer-normalize` | `qt fix:composer` | composer-normalize |

## Error Handling

Common scenarios for all commands:
- Tool binary not found: Guide user to run `composer install`
- Configuration file missing: Show expected path and suggest checking installation
- Process execution failure: Forward original error with helpful context
- Invalid options: Show command help with available options

## Testing Strategy

- Integration tests with real tool binaries for each command
- Mock testing for process execution error scenarios
- Validation of command registration and help text
- End-to-end testing with sample TYPO3 projects
- Performance testing with various project sizes

## Performance Considerations

- Minimal command overhead (direct process forwarding)
- Efficient configuration path resolution
- No unnecessary file system operations
- Proper resource cleanup after process execution

## Security Considerations

- Safe argument passing to prevent command injection
- Validation of user-provided paths and options
- Secure handling of configuration file paths
- Proper escaping of arguments passed to underlying tools

## Dependencies

All dependencies inherited from BaseCommand:
- `symfony/console`: ^6.0|^7.0
- `symfony/process`: ^6.0|^7.0

## Risk Assessment

**Low Risk:**
- Simple command forwarding with minimal logic
- Well-established tool binaries and configurations
- Proven Symfony Process component for execution

**Mitigation:**
- Comprehensive integration testing with real tools
- Clear error messages for common failure scenarios
- Validation of all tool binaries during development

## Future Enhancements

- Batch commands: `qt lint:all`, `qt fix:all`
- Interactive mode for confirmation prompts
- Progress reporting for long-running operations
- Custom tool configuration via project-specific settings
- Plugin system for additional tool integration

## Notes

- Keep command implementations minimal - focus on argument building and execution
- Ensure all existing tool functionality remains accessible
- Design commands to be easily discoverable via `qt list`
- Plan for future enhancement without breaking existing usage patterns
