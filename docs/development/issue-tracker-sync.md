# Issue Tracker Sync -- Maintainer Guide

This document describes the workflow for keeping GitHub issues, GitLab issues, local planning docs, and BMAD planning artifacts consistent.

## Tracker Roles

| Tracker | Audience | Purpose |
|---------|----------|---------|
| GitHub (`CPS-IT/quality-tools`) | Public, external contributors | Bug reports and feature requests; the project's public face |
| GitLab (`DevOps/testing/quality-tools`) | CPSIT members only | Internal issue management, MR workflow, CI/CD |
| `docs/plan/` | Developers | Detailed implementation specs and technical analysis |
| `_bmad-output/planning-artifacts/` | Tech leads | Sprint planning scope, epics, story ordering |

### Why Three Layers

GitHub is where users and external contributors file bugs. It must stay responsive.

GitLab is where internal MRs and CI run. It mirrors GitHub issues for internal tracking and holds GitLab-only items (internal quality debt, refactoring tasks that are not user-visible).

Local docs hold the full technical analysis that is too long for an issue description and needs to be versioned alongside the code.

BMAD artifacts provide sprint-level scope control. They reference both issue numbers and local docs but are not duplicated in the trackers.

## Issue Lifecycle

```
External user files on GitHub
        |
        v
Maintainer triages (within a few days):
  - Add label (bug / enhancement)
  - Post acknowledgment comment on GitHub
  - Create corresponding GitLab issue with link to GitHub issue
  - Create local doc: docs/plan/issue/NNN-... or docs/plan/feature/NNN-...
  - Add "GitLab: GL#N / GitHub: GH#N" to local doc header
  - Add "Planning doc: docs/plan/..." to both issue descriptions
        |
        v
Sprint planning (BMAD):
  - Reference both tracker numbers and local doc path in the story
  - Story format: [GL#N](url) / [GH#N](url) -- docs/plan/...
        |
        v
Implementation:
  - Work on a GitLab MR branch
  - MR description references both tracker numbers
        |
        v
MR merged:
  - Move local doc to done/ subfolder
  - Close GitLab issue with comment referencing MR
  - Close GitHub issue with comment referencing commit / release tag
  - Update BMAD story status to completed
```

## Triage Rules

### Which items go to GitHub (public)?

- Any bug or error a user of the package can observe (wrong exit code, CLI error, schema validation failure, incorrect tool execution)
- Any feature request that changes user-visible behaviour (new command, new config option, new tool integration)

### Which items stay in GitLab only?

- Internal refactoring (DI wiring, test architecture, code organization)
- Internal quality debt (test coverage gaps, static analysis warnings)
- Infrastructure changes (CI pipeline, deployment, tooling)

## Naming Conventions

### Local doc metadata header

Every local issue/feature doc that has a tracker entry must include tracker refs at the top:

```markdown
# Issue NNN: Title

- **Status:** Open
- **GitLab:** GL#N (url)
- **GitHub:** GH#N (url)        <- omit line if GitLab-only
- **Resolved by:** GH PR#N (merged YYYY-MM-DD)  <- add when resolved
```

### GitLab / GitHub issue descriptions

Every issue description must include a planning doc reference once one exists:

```
Planning doc: `docs/plan/issue/NNN-title.md`
```

### `docs/plan/index.md` Feature and Issue tables

Add `(GL#N / GH#N)` after the file link in the last column once tracker issues exist:

```markdown
| 025 | PathResolutionService Tool Paths Structure | Open | [...](issue/025-...) (GL#9 / GH#7) |
```

### BMAD stories

Reference format in story files:

```markdown
## Tracker References
- GitLab: [GL#N](url)
- GitHub: [GH#N](url)
- Planning doc: `docs/plan/issue/NNN-title.md`
```

## Sync Health Check

Run this check at the start of each sprint planning session:

1. List all open GitHub issues: `gh issue list --repo CPS-IT/quality-tools --state open`
2. For each open GitHub issue, verify:
   - A corresponding GitLab issue exists
   - A local planning doc exists (or a triage decision is made within that session)
3. List all local docs in `docs/plan/issue/` and `docs/plan/feature/` that are not in `done/`
4. For each open local doc, verify:
   - A GitLab issue exists (for user-visible items: also a GitHub issue)
5. Any GitHub issue older than 30 days without a planning doc must be triaged or explicitly deferred with a comment.

## Current Issue Map

See `tmp/gitlab-sync-overview.md` for the full cross-reference snapshot generated 2026-05-06.

The definitive live state is always the combination of:
- Open issues on GitHub: https://github.com/CPS-IT/quality-tools/issues
- Open issues on GitLab: https://gitlab.321.works/DevOps/testing/quality-tools/-/issues
- Open local docs in `docs/plan/issue/` and `docs/plan/feature/` (excluding `done/`)

## Closing Issues

When closing an issue after a merge, use this comment template on both GitHub and GitLab:

```
Fixed in MR !N / commit SHA (released in vX.Y.Z).

Planning doc: `docs/plan/issue/NNN-title.md`
```

Never close an issue without verifying the fix is in merged code. Check:
1. The local doc is in `done/`
2. The relevant tests pass
3. The MR is merged to the default branch
