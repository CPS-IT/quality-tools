CPSIT Quality Tools
===================

A collection of preconfigured quality assurance tools for TYPO3 projects, featuring a simple command-line interface that transforms complex tool commands into user-friendly shortcuts.

## Installation

```shell
composer require --dev cpsit/quality-tools
```

## Included Quality Tools

| Tool                                              | Description                                            |
|---------------------------------------------------|--------------------------------------------------------|
| [a9f/typo3-fractor][typo3-fractor]                | TYPO3-specific code modernization and refactoring tool |
| [armin/editorconfig-cli][editorconfig-cli]        | Command line tool for EditorConfig validation          |
| [codeception/codeception][codeception]            | Full-stack testing PHP framework                       |
| [ergebnis/composer-normalize][composer-normalize] | Composer plugin to normalize composer.json files       |
| [helmich/typo3-typoscript-lint][typoscript-lint]  | Linter for TYPO3 TypoScript files                      |
| [phpstan/phpstan][phpstan]                        | Static analysis tool for PHP                           |
| [phpunit/php-code-coverage][php-code-coverage]    | Code coverage information for PHP                      |
| [ssch/typo3-rector][typo3-rector]                 | TYPO3-specific automated code upgrades and refactoring |
| [typo3/coding-standards][coding-standards]        | TYPO3 coding standards and code style tools            |
| [typo3/testing-framework][testing-framework]      | Testing framework for TYPO3 extensions                 |

## Table of Contents

### User Guide
- [User Guide](docs/user-guide/) - Complete guide for installing and using the CLI tool

### Tool Configuration
- [Fractor](docs/Fractor.md) - TYPO3 Fractor configuration and usage
- [PHP CS Fixer](docs/PhpCsFixer.md) - PHP coding standards fixer configuration
- [PHPStan](docs/Phpstan.md) - Static analysis tool configuration
- [Rector](docs/Rector.md) - TYPO3 Rector configuration and usage
- [TypoScript Lint](docs/TypoScriptLint.md) - TypoScript linting configuration

### Project Planning
- [Planning Documentation](docs/plan/) - MVP implementation roadmap and feature breakdown

[typo3-fractor]: https://packagist.org/packages/a9f/typo3-fractor
[editorconfig-cli]: https://packagist.org/packages/armin/editorconfig-cli
[codeception]: https://packagist.org/packages/codeception/codeception
[composer-normalize]: https://packagist.org/packages/ergebnis/composer-normalize
[typoscript-lint]: https://packagist.org/packages/helmich/typo3-typoscript-lint
[phpstan]: https://packagist.org/packages/phpstan/phpstan
[php-code-coverage]: https://packagist.org/packages/phpunit/php-code-coverage
[typo3-rector]: https://packagist.org/packages/ssch/typo3-rector
[coding-standards]: https://packagist.org/packages/typo3/coding-standards
[testing-framework]: https://packagist.org/packages/typo3/testing-framework
