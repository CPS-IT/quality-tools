# Sprint Change Proposal - 2026-05-19

**Project:** quality-tools
**Trigger:** GitLab issue #7 not tracked in planning documents
**Scope:** Minor
**Status:** Approved

---

## Section 1: Issue Summary

A developer placing `typoscript-lint.yaml` (the `.yaml` extension variant) in their project
root to override the default typoscript-lint configuration gets a schema validation failure.
Qt incorrectly validates the file against the quality-tools JSON schema (`quality-tools.json`)
instead of treating it as a typoscript-lint tool config. The workaround -- renaming the file
to `.typoscript-lint.yml` and pointing to it via `.quality-tools.yaml` -- is non-obvious and
contradicts user expectation.

**Root cause:** `ConfigurationHierarchy::TOOL_CONFIG_FILES` and `FILE_PATTERNS['tool_specific']`
only register `typoscript-lint.yml`; the `.yaml` extension is absent. A secondary defect causes
the unrecognized file to reach `ConfigurationValidator` (quality-tools.json schema) rather than
failing silently or being ignored.

**Discovery:** Reported by Ingo Dirscherl (GL#7, opened 2026-04-14), assigned to d.wenzel.
Already assigned to the Epic 2 milestone in GitLab but absent from all planning documents.
Discovered during review before merging story 1.1.

---

## Section 2: Impact Analysis

- **Epic impact:** Epic 2 (Platform Stability & Defect Resolution) -- one story added.
  No epics affected structurally.
- **Story impact:** Stories 2.1-2.4 are unchanged. New Story 2.5 added within existing
  Epic 2 scope.
- **PRD conflict:** None. The PRD Phase 1 Priority 1 section already covers open issue triage
  and defect resolution.
- **Architecture conflict:** None. The fix is contained to `ConfigurationHierarchy.php`
  constant arrays; no architectural pattern changes.
- **UI/UX conflict:** N/A (CLI tool).
- **Technical impact:** Fix localized to `src/Configuration/ConfigurationHierarchy.php`
  plus investigation of secondary code path to `ConfigurationValidator`.

---

## Section 3: Recommended Approach

**Selected: Option 1 - Direct Adjustment**

Add Story 2.5 to Epic 2. The fix is contained, the epic structure already accommodates
bug-fix stories, and no other stories or documents require modification.

**Effort:** Low. Primary fix is two constant arrays in one file.
**Risk:** Low. The fix adds missing entries; it does not change existing behavior.
**Timeline impact:** None to existing stories.

---

## Section 4: Detailed Change Proposals

### Change A: epics.md - Story 2.5 added after Story 2.4

New story appended to the Epic 2 section:

```
Story 2.5: Fix typoscript-lint.yaml config overwrite validation error (GL#7)

As a developer,
I want to place a typoscript-lint.yaml file in my project root to override the default
typoscript-lint configuration,
So that I can customize typoscript-lint rules with the standard filename without getting
a schema validation error.
```

Full acceptance criteria and tasks in story file (see Change C).

### Change B: sprint-status.yaml

- epic-2: backlog -> in-progress
- Added: 2-5-fix-typoscript-lint-yaml-config-overwrite-validation-error: ready-for-dev  # GL#7
- last_updated: 2026-05-19

### Change C: New story file

`_bmad-output/implementation-artifacts/2-5-fix-typoscript-lint-yaml-config-overwrite-validation-error.md`

Full story with acceptance criteria, tasks, dev notes, and references.

---

## Section 5: Implementation Handoff

**Scope classification:** Minor -- direct implementation by Developer agent.

**Branch:** `bugfix/2-5-fix-typoscript-lint-yaml-config-overwrite-validation-error`
**Base:** `develop`

**Success criteria:**
- `typoscript-lint.yaml` in project root is recognized as the typoscript-lint config
- No false schema validation error when the file is present
- New unit and integration tests pass
- All five quality gates pass with zero errors
- GL#7 can be closed

**Next step:** Run `bmad-dev-story` on the story file to begin implementation.
