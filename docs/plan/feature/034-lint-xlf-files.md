# Feature 034: XLF / XLIFF File Linting

- **Status:** Open
- **GitLab:** GL#4 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/4)
- **GitHub:** GH#4 (https://github.com/CPS-IT/quality-tools/issues/4)
- **Reporter:** IngoMueller / i.dirscherl (2026-01-21)

## Description

Add a `qt lint:xlf` command (and optionally `qt fix:xlf`) that validates XLIFF translation files used in TYPO3 extensions. TYPO3 projects heavily rely on `.xlf` files for translations; malformed files cause runtime errors that are hard to debug.

The suggestion is to wrap `Symfony\Component\Translation\Command\XliffLintCommand`, which is already a Symfony standard and available as a standalone validator.

## Problem Statement

TYPO3 extensions use `.xlf` files for translations. These files are XML-based and can contain syntax errors or structural violations (missing `<source>`, wrong encoding, etc.) that are only detected at runtime. There is currently no `qt` command to lint them as part of the quality pipeline.

## Goals

- Add `qt lint:xlf` command that validates `.xlf` and `.xliff` files
- Integrate with the existing `qt` command structure (exit codes, --path, --config)
- Scan standard TYPO3 translation paths by default (e.g. `packages/*/Resources/Private/Language/`)

## Suggested Approach

Use `Symfony\Component\Translation\Command\XliffLintCommand` from `symfony/translation`. The component is likely already a transitive dependency via Symfony Console; confirm before adding a hard dependency.

## Acceptance Criteria

- [ ] `qt lint:xlf` validates all `.xlf` / `.xliff` files in configured paths
- [ ] Exit code 0 on clean, non-zero on violations
- [ ] --path option scopes validation to a specific directory
- [ ] Default scan paths include `packages/*/Resources/Private/Language/`
- [ ] Errors are reported with file path and line number
- [ ] Unit and integration tests added

## Dependencies

- Confirm availability of `symfony/translation` (or add as a dependency)
- Follows same command pattern as other `lint:*` commands (BaseCommand, runner architecture)

## Notes

- A `fix:xlf` command is not applicable (XLIFF files cannot be auto-fixed in a meaningful way)
- Consider whether this should be gated behind a feature flag or always-on
