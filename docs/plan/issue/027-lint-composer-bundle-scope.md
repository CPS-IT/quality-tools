# Issue 027: lint:composer Should Also Lint the quality-tools Bundle Itself

- **Status:** Open
- **GitLab:** GL#3 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/3)
- **GitHub:** GH#3 (https://github.com/CPS-IT/quality-tools/issues/3)
- **Reporter:** IngoMueller / i.dirscherl (2026-01-21)

## Problem Summary

`qt lint:composer` runs `composer normalize --dry-run` to validate the host project's `composer.json`. However, it does not validate the `composer.json` of the `cpsit/quality-tools` bundle itself.

This means the quality-tools package can have an unnormalized `composer.json` while `qt lint:composer` passes — a contradiction for a package that enforces quality standards.

## Current Behavior

`qt lint:composer` validates the `composer.json` of the project that has quality-tools installed as a dependency. It does not touch the quality-tools package's own `composer.json`.

## Expected Behavior

Option A: `qt lint:composer` also validates the quality-tools bundle's own `composer.json` when running from within the bundle.

Option B (simpler): The quality-tools `composer.json` is validated as part of the package's own `composer lint` script (already present as `composer lint:composer`), and the issue doc serves as a reminder to keep that CI step active.

## Analysis

Option B is likely already the case: the quality-tools package has its own `composer lint:composer` CI step. This issue may describe a confusion between "running qt as a project consumer" vs. "developing qt itself".

Action needed:
1. Verify that `composer lint:composer` in the quality-tools package CI pipeline validates the bundle's own `composer.json`
2. If yes: close as "works as designed" and document the distinction
3. If no: add a self-validation step

## Related

- Feature 015: Configuration Overwrites (done) - context for how qt discovers project root
