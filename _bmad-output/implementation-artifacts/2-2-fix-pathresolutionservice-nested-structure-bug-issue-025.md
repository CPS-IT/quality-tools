# Story 2.2: Fix PathResolutionService nested structure bug (Issue 025)

Status: done

## Story

As a developer,
I want tool-specific path overrides in `.quality-tools.yaml` to be resolved without error,
so that I can configure per-tool scan paths reliably without encountering a TypeError at runtime.

## Acceptance Criteria

1. Given a `.quality-tools.yaml` with a tool-specific path override in the nested `tools.<tool>.paths`
   structure, when any `qt lint:<tool>` or `qt fix:<tool>` command is executed, the command resolves
   the path override without throwing a TypeError or any uncaught exception.
2. The resolved paths are used for the tool invocation as configured.
3. Unit tests cover the nested structure path with at least: a valid override, an empty override, and
   a missing key.
4. Integration tests verify the full command resolves paths without TypeError when a tool-specific
   path override is present (at minimum for the rector runner via `describe()`).
5. All existing tests continue to pass.
6. All six quality gates pass with zero errors.

## Root Cause

`PathResolutionService::getToolPaths()` at `src/Service/PathResolutionService.php:75` returns
`$toolsConfig[$tool]['paths'] ?? []` where `paths` is the nested schema object
`{scan: [...], additional: [...], exclude: [...]}`.

When tool-specific paths are configured in `.quality-tools.yaml` like this (which is the correct
schema-compliant form, per `config/schema/quality-tools.json` definition `"tool_paths"`):

```yaml
quality-tools:
  tools:
    rector:
      paths:
        scan:
          - "custom-rector-src/"
```

`getToolPaths()` returns `['scan' => ['custom-rector-src/']]` instead of `['custom-rector-src/']`.
`getResolvedPathsForTool()` sees this non-empty result and returns it directly. Runners then pass
this nested array to `ProcessExecutor` as path arguments, which expects `list<string>`, causing a
TypeError.

The test fixture confirming the schema-correct format:
`tests/Fixtures/021-path-configuration/tool-specific-overrides/.quality-tools.yaml`

## Tasks / Subtasks

- [x] Fix `PathResolutionService::getToolPaths()` (AC: 1, 2)
  - [x] In `src/Service/PathResolutionService.php:75-79`, change:
    ```php
    return $toolsConfig[$tool]['paths'] ?? [];
    ```
    to:
    ```php
    return $toolsConfig[$tool]['paths']['scan'] ?? [];
    ```
  - [x] This aligns the return value with what all callers expect: a flat `list<string>` of scan
        paths, matching the `"tool_paths".scan` field in `config/schema/quality-tools.json`

- [x] Update existing unit tests that use schema-incorrect flat `paths` arrays (AC: 3, 5)
  - [x] In `tests/Unit/Service/PathResolutionServiceTest.php`, fix `testGetToolPathsWithConfiguredPaths`:
    - Change test data from `'paths' => ['src/', 'packages/']` (flat, invalid schema)
    - To `'paths' => ['scan' => ['src/', 'packages/']]` (nested, schema-correct)
    - Assert `['src/', 'packages/']` as before
  - [x] Fix `testGetResolvedPathsForToolWithConfiguredPaths` similarly:
    - Change `'paths' => ['custom/', 'special/']` to `'paths' => ['scan' => ['custom/', 'special/']]`
    - Assert `['custom/', 'special/']` (no change to expectation, the fix makes this work correctly)

- [x] Add new unit tests for the nested structure variants (AC: 3)
  - [x] `testGetToolPathsWithValidNestedStructure`:
    - Input: `tools.rector.paths.scan = ['custom/']`
    - Expected: `['custom/']`
  - [x] `testGetToolPathsWithEmptyScanKey`:
    - Input: `tools.rector.paths = ['scan' => []]`
    - Expected: `[]`
  - [x] `testGetToolPathsWithMissingPathsKey`:
    - Input: `tools.rector = ['enabled' => true]` (no paths key)
    - Expected: `[]`

- [x] Fix behavior-documenting integration tests in `ToolCommandPathConfigurationTest` (AC: 4, 5)
  - File: `tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php`
  - [x] Rename `rectorToolSpecificPathsReturnNestedStructure` (line 116) to
        `rectorToolSpecificPathsReturnFlatList`; remove the "Documents current behavior" comment;
        change assertions to verify:
    - `$resolvedPaths` is a `list<string>` (no `scan` key present)
    - `assertContains('custom-rector-src/', $resolvedPaths)` (the actual path value)
  - [x] Do the same for `phpstanToolSpecificPathsReturnNestedStructure` -> `phpstanToolSpecificPathsReturnFlatList`
  - [x] In `rectorRunnerDescribeReturnsNonEmptyPathsWithToolSpecificConfig` (line 369), add assertion
        that `$description->targetPaths` contains flat strings (not nested arrays):
    - `assertContains('custom-rector-src/', $description->targetPaths)` or assert no array elements
  - [x] In `phpstanRunnerDescribeReturnsNonEmptyPathsWithToolSpecificConfig` (line 391), same addition

- [x] Verify all quality gates pass (AC: 6)
  - [x] `composer lint:composer`
  - [x] `composer lint:editorconfig`
  - [x] `composer lint:php`
  - [x] `composer lint:rector`
  - [x] `composer sca:php`
  - [x] `composer test`

## Dev Notes

### Scope: PathResolutionService and its tests only

Per `_bmad-output/planning-artifacts/architecture.md` (Issue 025 Resolution section):
"The fix is scoped to PathResolutionService and its tests; no architectural change required."

Do NOT touch `ToolConfigService::getToolPaths()` -- it is a separate method not involved in
the path resolution flow leading to runners. It returns the raw nested structure for its own
callers (config display), which may be correct for their purpose.

### Key files

- `src/Service/PathResolutionService.php` -- one-line fix in `getToolPaths()`
- `tests/Unit/Service/PathResolutionServiceTest.php` -- update 2 tests, add 3 tests
- `tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php` -- fix 2 behavior-documenting
  tests, strengthen 2 runner tests; no new fixtures needed
- `tests/Fixtures/021-path-configuration/tool-specific-overrides/.quality-tools.yaml` -- existing
  fixture already has correct nested structure; no changes needed

### Existing tests that document the wrong behavior (will fail until the fix is applied)

In `ToolCommandPathConfigurationTest`:
- `rectorToolSpecificPathsReturnNestedStructure` (line 116): currently asserts `assertArrayHasKey('scan', $resolvedPaths)` -- this assertion documents the bug and must change to assert the flat list
- `phpstanToolSpecificPathsReturnNestedStructure` (line 140): same

These tests will guide the fix: once they assert the correct behavior, the fix must make them green.

### Architecture constraints

- Do NOT add path validation in `getToolPaths()` -- security validation happens at entry points
  only (commands and config loaders), per ADRs 0001-0004
- Do NOT read the `additional` or `exclude` sub-keys from `tool_paths` in this story -- that
  scope belongs to Story 6.2 (additional scan paths and tool-specific path overrides)
- The `getResolvedPathsForTool()` method flow does not change: it still prefers tool-specific
  paths over PathScanner if non-empty

### Testing patterns in this codebase

Unit tests: construct services directly, use vfsStream (`org/bovigo/vfs`) where filesystem is
involved, no DI container.

Integration tests: use `TestHelper::createTempDirectory()`, `TestHelper::createVendorStructure()`,
copy fixture directories to temp dir, set `QT_PROJECT_ROOT` via `putenv()`, call `VendorDirectoryDetector::clearCache()` in tearDown.

The existing `PathResolutionServiceTest` does NOT use vfsStream (no filesystem ops), so no change
in test setup.

### References

- Architecture: `_bmad-output/planning-artifacts/architecture.md` -- "Issue 025 Resolution" section
- Epic 2 story definition: `_bmad-output/planning-artifacts/epics.md` -- Story 2.2
- JSON schema for `tool_paths`: `config/schema/quality-tools.json` definition `"tool_paths"`
- Fixture for test data: `tests/Fixtures/021-path-configuration/tool-specific-overrides/.quality-tools.yaml`
- Existing integration test: `tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php`
- Existing unit test: `tests/Unit/Service/PathResolutionServiceTest.php`

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

One-line fix in `PathResolutionService::getToolPaths()`: changed `$toolsConfig[$tool]['paths'] ?? []`
to `$toolsConfig[$tool]['paths']['scan'] ?? []`. This aligns the return value with the schema-correct
nested YAML structure `tools.<tool>.paths.scan: [...]` and returns a flat `list<string>` as callers
expect.

Updated three existing tests that used the invalid flat `paths` structure, added three new unit tests
(valid nested, empty scan key, missing paths key), renamed and corrected two behavior-documenting
integration tests to assert flat list output, and strengthened two runner `describe()` tests with
`assertContains` checks on the actual path strings.

Also found and fixed one additional test in `UnifiedConfigurationSimpleTest` using the same incorrect
flat structure (`testPathConfigurationWithServices`).

All 1155 tests pass. All six quality gates pass with zero errors.

### File List

- src/Service/PathResolutionService.php
- tests/Unit/Service/PathResolutionServiceTest.php
- tests/Unit/Configuration/UnifiedConfigurationSimpleTest.php
- tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php

### Review Findings

Code review 2026-06-06. Layers: Blind Hunter, Edge Case Hunter, Acceptance Auditor.
Gates re-verified during review: PHPStan clean, PHP-CS-Fixer clean, 69 targeted tests
pass (the 3 changed test files). The one-line fix, all named test renames, the 3 new
unit tests, the 2 updated unit tests, and the 2 strengthened describe() tests are all
present and correct; architecture constraints respected (ToolConfigService untouched, no
validation added, additional/exclude not read, resolution flow unchanged).

Patch:

- [x] [Review][Patch] Strengthen integration assertions to prove a flat list, not mere containment. Replaced assertArrayNotHasKey('scan', ...) with a new assertFlatStringList() helper (asserts array_is_list and string-only elements) on the two resolved-path tests and both describe() tests [tests/Integration/Console/Command/ToolCommandPathConfigurationTest.php]
- [x] [Review][Patch] Fix story AC6 gate-count wording: changed "five quality gates" to "six" to match the task list and Completion Notes [_bmad-output/implementation-artifacts/2-2-fix-pathresolutionservice-nested-structure-bug-issue-025.md:22]

Defer:

- [x] [Review][Defer] tools.<tool>.paths with additional/exclude but no scan key returns [] (configured paths silently ignored); no test for the "paths present, scan absent" shape [src/Service/PathResolutionService.php:78] -- deferred, explicitly belongs to Story 6.2 per Dev Notes
- [x] [Review][Defer] A non-list scan (e.g. string) reaching getToolPaths via the deferred-validation Configuration path causes string-offset access [src/Service/PathResolutionService.php:78] -- deferred, pre-existing concern of the unvalidated config path; validated load rejects it
- [x] [Review][Defer] Explicit scan: [] silently falls back to the global PathScanner instead of scanning nothing [src/Service/PathResolutionService.php:59] -- deferred, pre-existing getResolvedPathsForTool !empty() semantics, unchanged by this fix
- [x] [Review][Defer] No unit test for an unknown/absent tool name or a second tool (only rector/phpstan exercised; a $tool-ignoring impl would still pass) [tests/Unit/Service/PathResolutionServiceTest.php:416] -- deferred, minor coverage gap beyond AC3's required cases

Dismissed (6): flat list paths: [...] is schema-invalid (object required) so never a supported format; PhpStanRunner has no [$projectRoot] fallback but getResolvedPathsForTool already falls back to global PathScanner so it never receives []; end-to-end TypeError not exercised through ProcessExecutor but AC4 explicitly requires only describe() coverage which is present; diff includes .claude/ and docs files from commit f578fe5 (artifact of the chosen diff range, not a defect; doc change is correct); story "Key files" subsection omits UnifiedConfigurationSimpleTest (disclosed in File List and Notes); self-reported "1155 tests / gates pass" (now independently re-verified).
