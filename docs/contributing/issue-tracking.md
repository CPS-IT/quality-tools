# Issue Tracking for Contributors

This document explains how issues and feature requests are managed in the quality-tools project.

## Where to File Issues

**File all bug reports and feature requests on GitHub:**
https://github.com/CPS-IT/quality-tools/issues

GitHub is the public-facing tracker. The project is an open-source Composer package and welcomes contributions from anyone. The maintainers monitor GitHub issues and respond there.

## What Happens After You File

1. A maintainer triages the issue, adds a label (`bug` or `enhancement`), and posts an acknowledgment comment, usually within a few days.
2. If accepted, a planning document is created in the repository under `docs/plan/issue/` or `docs/plan/feature/`. The issue description will be updated with a link to the planning doc.
3. The issue is scheduled into a development iteration. For user-visible bugs this is normally the next sprint; for features it depends on priority.
4. When the fix or feature is merged, the issue is closed with a reference to the commit or release.

## Labels

| Label | Meaning |
|-------|---------|
| `bug` | Something does not work as documented |
| `enhancement` | New feature or improvement request |
| `documentation` | Correction or addition to docs only |

## Bug Reports

A useful bug report includes:

- The `qt` command you ran
- The `vendor/bin/qt --version` output
- The contents of your `.quality-tools.yaml` (if you have one)
- The full error message or unexpected output
- What you expected to happen

## Feature Requests

A useful feature request includes:

- The use case: what problem are you trying to solve?
- A suggested command or configuration syntax (optional but helpful)
- Whether you would be willing to contribute an implementation

## Security Issues

Do not file security vulnerabilities as public GitHub issues. Contact the maintainers directly via the email in `composer.json`.

## Contribution Workflow

If you want to implement a fix or feature yourself:

1. Comment on the relevant GitHub issue so maintainers know you are working on it.
2. Fork the repository and create a branch named `issue-NNN-short-description`.
3. Follow the coding standards described in `CLAUDE.md` and `README.md`.
4. Open a pull request referencing the issue number (`Closes #NNN`).
5. All CI checks must pass before a PR is reviewed.
