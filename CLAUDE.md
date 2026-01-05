# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Information
- TYPO3 quality tools package for code analysis and refactoring
- Composer package: `cpsit/quality-tools`
- Targets PHP 8.3+ and TYPO3 v13.4
- Collection of preconfigured quality assurance tools for TYPO3 projects
- **Complete CLI interface implemented** - Use `vendor/bin/qt` commands as shortcuts
- **Status: Completed MVP** - All 10 tool commands implemented and tested (96.91% coverage)

## Architecture
This package provides standardized configurations for:
- **Rector**: TYPO3-specific code modernization and automated refactoring
- **Fractor**: TYPO3-specific TypoScript and code modernization
- **PHPStan**: Static analysis at level 6 for PHP code quality
- **PHP CS Fixer**: Code style fixing based on TYPO3 coding standards
- **TypoScript Lint**: Linting for TYPO3 TypoScript files
- **EditorConfig CLI**: EditorConfig validation
- **Codeception**: Testing framework integration
- **Composer Normalize**: Composer.json normalization

## Configuration Files
All tools are preconfigured in the `config/` directory:
- `rector.php` - TYPO3 Rector configuration targeting PHP 8.3 and TYPO3 13
- `fractor.php` - TYPO3 Fractor configuration for TypoScript modernization
- `phpstan.neon` - PHPStan configuration at level 6
- `php-cs-fixer.php` - TYPO3 coding standards configuration
- `typoscript-lint.yml` - TypoScript linting rules with 2-space indentation

## Tool Usage Commands

**CLI Commands (Recommended):**
Use the simple `qt` command shortcuts:
```bash
# Lint commands (analysis only)
vendor/bin/qt lint:rector          # Rector dry-run analysis
vendor/bin/qt lint:phpstan         # PHPStan static analysis
vendor/bin/qt lint:php-cs-fixer    # PHP CS Fixer analysis
vendor/bin/qt lint:fractor         # Fractor TypoScript analysis
vendor/bin/qt lint:typoscript      # TypoScript Lint validation
vendor/bin/qt lint:composer        # Composer.json validation

# Fix commands (apply changes)
vendor/bin/qt fix:rector           # Apply Rector fixes
vendor/bin/qt fix:php-cs-fixer     # Apply PHP CS Fixer fixes
vendor/bin/qt fix:fractor          # Apply Fractor fixes
vendor/bin/qt fix:composer         # Normalize composer.json

# All commands support --help, --config, --path, and --verbose options
vendor/bin/qt lint:phpstan --help
```

**Direct Tool Access (Alternative):**
These commands should be run from the TYPO3 project root (not this package directory):

### Rector (PHP Code Modernization)
```bash
# Dry run to see proposed changes
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run

# Apply changes
app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php
```

### Fractor (TypoScript Modernization)
```bash
# Dry run to see proposed changes
app/vendor/bin/fractor process --dry-run -c app/vendor/cpsit/quality-tools/config/fractor.php

# Apply changes
app/vendor/bin/fractor process -c app/vendor/cpsit/quality-tools/config/fractor.php
```

### PHPStan (Static Analysis)
```bash
# Analyze with default configuration
app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon

# Custom level
app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon --level=2

# Custom path
app/vendor/bin/phpstan analyse -c phpstan.neon ./path/to/extension
```

### PHP CS Fixer (Code Style)
```bash
# Dry run to see proposed changes
app/vendor/bin/php-cs-fixer fix --dry-run --config=app/vendor/cpsit/quality-tools/config/php-cs-fixer.php

# Fix code style issues
app/vendor/bin/php-cs-fixer fix --config=app/vendor/cpsit/quality-tools/config/php-cs-fixer.php
```

### TypoScript Lint
```bash
# Lint all TypoScript files
vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml

# Custom path
vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml --path ./path/to/extension/Configuration/TypoScript/
```

## Path Configuration
The configurations automatically detect the TYPO3 project root and scan:
- `config/system/` - System configuration files
- `packages/` - Custom packages and extensions
- `config/sites/` - Site configuration (Fractor only)

## Quality Standards
- PHP version: 8.3+ (configured in Rector)
- TYPO3 version: 13.4.x (configured in Rector ExtEmConf)
- PHPStan level: 6 (strict analysis)
- Indentation: 2 spaces for TypoScript, follows TYPO3 standards for PHP
- Code style: TYPO3 coding standards via php-cs-fixer

## Code Quality Standards
- **String Constants**: Replace repeated string literals with typed class constants
  - Use `private const string KEY_NAME = 'value'` for string constants
  - Use `private const int KEY_NAME = 123` for integer constants
  - Use `private const array KEY_NAME = [...]` for array constants
  - Group related constants logically within classes
  - Always use descriptive, SCREAMING_SNAKE_CASE names for constants

## Implementation Completion Standards

**CRITICAL:** Implementation of features and bug fixing is **NOT** finished before any failing tests and linting issues are fixed.

**Definition of Done:**
- all tasks in the current feature specification are completed
- all success criteria are met
- All unit tests must pass
- All integration tests must pass
- All linting checks must pass without errors
- Code coverage requirements must be met
- Documentation must be updated and accurate

**Quality Gate Requirements:**
- No failing test suites
- No linting errors
- Proper error handling and edge case coverage

This ensures code quality, maintainability, and reliability before any feature is considered complete.

## Communication Guidelines

**Tone and Language:**

- Maintain modest, factual tone without boasting or business hyperbole
- Use precise technical language without exaggeration
- Avoid superlatives and marketing-style claims
- Focus on concrete capabilities rather than promotional language

**Timeline References:**

- Avoid specific "Week X" statements in planning documents
- Use relative terms like "initial phase", "later phase", "after prototype validation"
- Focus on dependencies and logical sequencing rather than calendar commitments

# CRITICAL FORMATTING REQUIREMENTS

**NO UNICODE CHARACTERS EVER:**

- **NEVER** use Unicode icons, symbols, or special characters in any files
- **NEVER** use checkmarks (✓, ✅), crosses (✗, ❌), arrows (→), or any emoji
- **NEVER** use special Unicode bullets (•, ◦, ▪) or decorative characters
- Use only standard ASCII characters: letters, numbers, basic punctuation
- Use text alternatives: "Completed", "Done", "Failed", "Todo", "[x]", "[ ]"
- This applies to ALL files: code, documentation, comments, commit messages

**Acceptable Alternatives:**
- Instead of ✅: "Completed", "Done", "[x]"
- Instead of ❌: "Failed", "Error", "[ ]"
- Instead of →: "->" or "to"
- Instead of •: "-" or "*"
- Instead of any emoji: descriptive text
