# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with TYPO3-aligned major versions.

## Version Numbering Strategy

Starting with version 13.0, the major version number aligns with the targeted TYPO3 major version:
- quality-tools 13.x targets TYPO3 v13
- quality-tools 14.x targets TYPO3 v14

See [ADR-0006](docs/architecture/0006-typo3-aligned-trunk-based-versioning.md) for details.

## [Unreleased]

## [14.0.0] - 2026-09-06

### Added
- Initial TYPO3 v14 support on main branch
- CHANGELOG.md to track version history
- Versioning strategy documentation in README

### Changed
- Default Rector level set to 'typo3-14'
- Default TYPO3 version set to '14.0'
- All configuration templates updated to TYPO3 v14 defaults
- config/rector.php now points to rector-typo3-14.php

### Migration Notes
- Projects using TYPO3 v13 should use quality-tools ^13.0
- Projects using TYPO3 v14 should use quality-tools ^14.0
- The 13.x branch continues to receive bugfixes for TYPO3 v13

## [13.1.2] - 2026-09-06

### Fixed
- Complete Rector default level fix for 13.x release line
- Updated DEFAULT_RECTOR_LEVEL constant to 'typo3-13' in ConfigurationInterface
- Updated test expectations to match typo3-13 default

### Notes
- This release is available on the 13.x support branch
- Completes the fix started in 13.1.1

## [13.1.1] - 2026-09-05

### Fixed
- Corrected default Rector target to TYPO3 v13 for 13.x release line
- config/rector.php now points to rector-typo3-13.php

## [13.1.0] - 2026-09-04

### Added
- Versioned Rector configurations for TYPO3 v13 and v14
- config/rector-typo3-13.php for TYPO3 v13 rules
- config/rector-typo3-14.php for TYPO3 v14 rules
- Support for explicit version selection via --config flag
- ADR-0006: TYPO3-Aligned Trunk-Based Versioning and Branching Strategy

### Changed
- config/rector.php is now an alias pointing to active TYPO3 target
- Rector configuration supports explicit version targeting

## [13.0.0] - 2026-07-03

### Added
- Initial release with TYPO3-aligned versioning
- Complete CLI interface with 10 tool commands
- Unified YAML configuration system
- Flexible path configuration for complex project structures
- Dynamic resource optimization for all tools
- Configuration hierarchy support
- Preconfigured quality tools:
  - Rector (TYPO3-specific code modernization)
  - Fractor (TypoScript modernization)
  - PHPStan (static analysis at level 6)
  - PHP CS Fixer (TYPO3 coding standards)
  - TypoScript Lint
  - EditorConfig CLI
  - Composer Normalize

### Changed
- Version jump from 0.2.0 to 13.0.0 to align with TYPO3 versioning
- This is NOT the 13th major breaking change - see versioning strategy above

### Migration from 0.x
- The package now follows TYPO3-aligned versioning
- Use quality-tools ^13.0 for TYPO3 v13 projects
- All existing functionality from 0.2.0 is preserved

## [0.2.0] - Earlier

### Added
- MVP features and initial tool integration

## [0.1.0] - Earlier

### Added
- Initial experimental release

[Unreleased]: https://github.com/CPS-IT/quality-tools/compare/14.0.0...HEAD
[14.0.0]: https://github.com/CPS-IT/quality-tools/compare/13.1.2...14.0.0
[13.1.2]: https://github.com/CPS-IT/quality-tools/compare/13.1.1...13.1.2
[13.1.1]: https://github.com/CPS-IT/quality-tools/compare/13.1.0...13.1.1
[13.1.0]: https://github.com/CPS-IT/quality-tools/compare/13.0.0...13.1.0
[13.0.0]: https://github.com/CPS-IT/quality-tools/compare/0.2.0...13.0.0
[0.2.0]: https://github.com/CPS-IT/quality-tools/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/CPS-IT/quality-tools/releases/tag/0.1.0
