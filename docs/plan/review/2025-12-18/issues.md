# Quality Tools Issues - TYPO3 Project

**Related Documents:**
- [Detailed Post-Mortem Review](review.md) - Root cause analysis and architectural lessons learned
- [Testing Strategy Analysis](testing-gaps-analysis.md) - Testing gaps that missed these issues
- [Comprehensive Testing Plan](comprehensive-testing-strategy-summary.md) - Future testing strategy

This document contains findings from executing all lint commands against the a realworld TYPO3 project.

## Summary

All quality tools encountered critical issues when analyzing the TYPO3 project:

- **Memory exhaustion**: PHPStan and PHP CS Fixer both exceeded PHP memory limit (128M)
- **Missing dependencies**: composer-normalize executable not found
- **Tool crashes**: Fractor crashed with TypeError in YAML parsing
- **Command interface issues**: TypoScript lint doesn't support --path option as expected

**Result**: 5 out of 6 tools completely non-functional, 1 tool severely degraded

## Individual Issue Reports

Each issue has been analyzed in detail with root cause analysis and solution recommendations:

1. [Issue 001: PHPStan Memory Exhaustion](../issue/001-phpstan-memory-exhaustion.md) - High Priority, Low Effort
2. [Issue 002: PHP CS Fixer Memory Exhaustion](../issue/002-php-cs-fixer-memory-exhaustion.md) - High Priority, Low Effort
3. [Issue 003: Fractor YAML Parser Crash](../issue/003-fractor-yaml-parser-crash.md) - Medium Priority, Medium Effort
4. [Issue 004: TypoScript Lint Path Option](../issue/004-typoscript-lint-path-option.md) - Medium Priority, Low Effort
5. [Issue 005: Composer Normalize Missing](../issue/005-composer-normalize-missing.md) - High Priority, Low Effort
6. [Issue 006: Rector Performance](../issue/006-rector-performance-large-projects.md) - Low Priority, Medium Effort

## Detailed Findings

### 1. lint:rector [PARTIAL]
- **Status**: Working but slow ([Details](../issue/006-rector-performance-large-projects.md))
- **Issue**: Command appears functional but takes very long time to analyze entire project (>30 seconds)
- **Evidence**: Tool started analyzing files, found 24 files with changes
- **Recommendation**: Consider adding memory limit and timeout configurations

### 2. lint:phpstan [FAILED]
- **Status**: Memory exhaustion error ([Details](../issue/001-phpstan-memory-exhaustion.md))
- **Error**: `PHP Fatal error: Allowed memory size of 134217728 bytes exhausted`
- **Location**: `phar:///path/to/project/app/vendor/phpstan/phpstan/phpstan.phar/src/Cache/FileCacheStorage.php on line 73`
- **Message**: "PHPStan process crashed because it reached configured PHP memory limit: 128M"
- **Recommendation**: Need to increase memory limit with `--memory-limit` option or in php.ini

### 3. lint:php-cs-fixer [FAILED]
- **Status**: Memory exhaustion error ([Details](../issue/002-php-cs-fixer-memory-exhaustion.md))
- **Error**: `PHP Fatal error: Allowed memory size of 134217728 bytes exhausted`
- **Location**: `/path/to/project/app/vendor/friendsofphp/php-cs-fixer/src/Tokenizer/Tokens.php on line 1149`
- **Warning**: "No PHP version requirement found in composer.json"
- **Progress**: Started analysis on 435 files before crashing
- **Recommendation**: Increase memory limit and add PHP version to composer.json

### 4. lint:fractor [FAILED]
- **Status**: TypeError crash ([Details](../issue/003-fractor-yaml-parser-crash.md))
- **Error**: `TypeError: a9f\FractorYaml\SymfonyYamlParser::parse(): Return value must be of type array, string returned`
- **Location**: `/path/to/project/app/vendor/a9f/fractor-yaml/src/SymfonyYamlParser.php on line 18`
- **Progress**: Processed 47/3824 files before crash
- **Recommendation**: Bug in Fractor YAML parser - may need version update or configuration fix

### 5. lint:typoscript [FAILED]
- **Status**: Command interface error ([Details](../issue/004-typoscript-lint-path-option.md))
- **Error**: `The "--path" option does not exist`
- **Issue**: TypoScript linter doesn't support the --path option that our CLI tries to use
- **Available options**: `-c|--config`, `-f|--format`, `-o|--output`, `-e|--exit-code`, `--fail-on-warnings`
- **Recommendation**: Update TypoScriptLintCommand to not use --path option

### 6. lint:composer [FAILED]
- **Status**: Missing executable ([Details](../issue/005-composer-normalize-missing.md))
- **Error**: `sh: /path/to/project/app/vendor/bin/composer-normalize: No such file or directory`
- **Issue**: composer-normalize is not installed in the target project
- **Recommendation**: Either install composer-normalize in target project or handle gracefully when missing

## Recommendations

### Immediate Fixes Needed

1. **Memory Configuration**: Add memory limit options to PHPStan and PHP CS Fixer commands
2. **TypoScript Command**: Remove --path option usage in TypoScriptLintCommand
3. **Missing Dependencies**: Handle cases where tools are not installed in target project
4. **Error Handling**: Improve error messages and graceful degradation

### Configuration Improvements

1. Add memory limit options to command configurations
2. Add timeout handling for long-running processes
3. Add dependency checking before executing commands
4. Consider scoped analysis for large projects

### Project-Specific Issues

1. Target project needs PHP version specification in composer.json
2. composer-normalize should be added to dev dependencies
3. Memory limits need to be increased for this large TYPO3 project (>3800 files)

## Test Environment

- **Project Path**: `/path/to/project`
- **Project Type**: TYPO3 v13.4 with app/vendor structure
- **PHP Version**: 8.3.23
- **Files Analyzed**: 435 PHP files, 3824 total files
- **Memory Limit**: 128M (insufficient for project size)

---

*Generated: 2025-12-18*
