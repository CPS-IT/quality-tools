# Versioning and TYPO3 Compatibility

This page explains how CPSIT Quality Tools versions relate to TYPO3 versions, and how to pick the right one for your project.

## Versions Track TYPO3 Major Versions

Starting with version **13.0**, the quality-tools major version number matches the TYPO3 major version it targets:

| quality-tools version | Targets TYPO3 version |
|---|---|
| `13.x` | TYPO3 v13 |
| `14.x` | TYPO3 v14 |

This is a deliberate jump from the pre-1.0 `0.x` line used before this scheme was introduced; it is not 13 prior breaking releases of quality-tools itself. See [ADR-0006](../architecture/0006-typo3-aligned-trunk-based-versioning.md) for the full rationale.

## Selecting the Right Version

Require the version matching your project's TYPO3 version through Composer:

```bash
# For a TYPO3 v13 project
composer require --dev cpsit/quality-tools:^13.0

# For a TYPO3 v14 project
composer require --dev cpsit/quality-tools:^14.0
```

If you omit the version constraint, Composer resolves the latest release, which targets the newest supported TYPO3 major version - pin explicitly if your project is on an older, still-supported TYPO3 version.

## Support for Older TYPO3 Versions

Once quality-tools development moves on to target a new TYPO3 major version, the previous major version line keeps receiving **bugfixes only, no new features**, for as long as it is still needed. New tool integrations and features are only ever added for the currently targeted TYPO3 version.

A current support matrix (which quality-tools version(s) are actively maintained for which TYPO3 version(s)) is maintained alongside this documentation; check the project README for the up-to-date table.

## See Also

- [Installation Guide](installation.md)
- [ADR-0006: TYPO3-Aligned Trunk-Based Versioning and Branching Strategy](../architecture/0006-typo3-aligned-trunk-based-versioning.md)
