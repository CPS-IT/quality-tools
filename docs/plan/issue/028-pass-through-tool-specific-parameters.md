# Issue 028: Pass-Through of Tool-Specific Parameters (--format, --error-format, etc.)

- **Status:** Open
- **GitLab:** GL#8 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/8)
- **GitHub:** none (GitLab-only)
- **Reporter:** i.dirscherl (2026-04-17)

## Problem Summary

Users want to pass tool-specific output format parameters to `qt` commands so the tools can write machine-readable output to files that can be saved as CI pipeline artifacts. Examples:

- `qt lint:php-cs-fixer --format=checkstyle > report.xml`
- `qt lint:phpstan --error-format=junit > phpstan-report.xml`

Currently `qt` does not forward unknown/extra parameters to the underlying tool binary.

## Relation to Existing Work

This is a narrower, concrete instance of the deferred **Feature 016 (Unified Arguments/Options)**. That feature aims to standardize all CLI options across tools; this issue requests the minimal useful subset: the ability to pass arbitrary extra arguments through to the underlying tool.

Two possible approaches:

**Option A: Pass-through mode (quick win)**
Accept a trailing `--` separator and forward everything after it verbatim to the underlying tool. Example:
```
qt lint:phpstan -- --error-format=junit
```

**Option B: Explicit format options (Feature 016 scope)**
Add `--report-format` and `--report-file` options natively to all lint commands. This is already planned as part of the report pipeline (Epic 3) with `--report-format=json` and `--report-file=path`.

## Recommendation

Option A (pass-through with `--`) can be implemented independently as a short-term fix without requiring the full Feature 016 or Epic 3 work. It gives users full flexibility immediately.

Option B is the clean long-term solution and will land as part of Epic 3 (FR20/FR21: `--report-format` / `--report-file`).

## Acceptance Criteria (Option A)

- [ ] All `qt lint:*` and `qt fix:*` commands accept `--` followed by arbitrary extra arguments
- [ ] Extra arguments are appended verbatim to the underlying tool command
- [ ] `--help` documents the `--` pass-through behavior
- [ ] Unit tests for pass-through argument forwarding

## Related

- Feature 016: Unified Arguments/Options (deferred) - full standardization
- Epic 3: Report Pipeline Phase 1 - `--report-format` / `--report-file` options (FR20/FR21)
