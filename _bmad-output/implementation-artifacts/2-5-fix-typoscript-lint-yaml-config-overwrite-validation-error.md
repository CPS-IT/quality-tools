# Story 2.5: Fix typoscript-lint.yaml config overwrite validation error (GL#7)

Status: review

## Story

As a developer,
I want to place a `typoscript-lint.yaml` file in my project root to override the default
typoscript-lint configuration,
so that I can customize typoscript-lint rules with the standard filename without getting
a schema validation error.

## Acceptance Criteria

1. A `typoscript-lint.yaml` file in the project root is recognized as the typoscript-lint
   tool configuration and passed to the typoscript-lint binary via its config option.
2. Qt does not validate `typoscript-lint.yaml` against the quality-tools schema
   (`quality-tools.json`).
3. No validation error is reported due to a schema mismatch between the typoscript-lint
   config format and the quality-tools config schema.
4. `qt config:validate` continues to validate `.quality-tools.yaml` correctly without
   false positives from tool-specific config files in the project root.
5. A unit test covers the `.yaml` extension variant being recognized and mapped to the
   `typoscript-lint` tool in `ConfigurationHierarchy`.
6. An integration test verifies end-to-end command execution with a root-level
   `typoscript-lint.yaml` file succeeds without error.
7. All existing tests continue to pass without modification.
8. All five quality gates pass with zero errors.

## Tasks / Subtasks

- [x] Add `.yaml` extension to `ConfigurationHierarchy` constants (AC: 1, 2, 3)
  - [x] Add `'typoscript-lint.yaml'` to `FILE_PATTERNS['tool_specific']`
  - [x] Add `'config/typoscript-lint.yaml'` to `FILE_PATTERNS['tool_config_dir']`
  - [x] Add `'typoscript-lint.yaml'` to `TOOL_CONFIG_FILES['typoscript-lint']`
- [x] Investigate and fix the secondary path that routes unrecognized YAML files to
      `ConfigurationValidator` (AC: 2, 3)
  - [x] Trace why `typoscript-lint.yaml` (before the fix) reaches `ConfigurationValidator`
        rather than failing silently; identify the code path
  - [x] Apply a guard if needed so only known quality-tools config filenames are validated
        against the quality-tools JSON schema
- [x] Add unit tests (AC: 5, 7)
  - [x] `ConfigurationHierarchyTest`: assert `typoscript-lint.yaml` resolves to tool
        `typoscript-lint` via `getToolForConfigFile`
  - [x] `ConfigurationHierarchyTest`: assert `typoscript-lint.yaml` appears in
        `FILE_PATTERNS['tool_specific']` and `TOOL_CONFIG_FILES['typoscript-lint']`
- [x] Add integration test (AC: 6, 7)
  - [x] Verify `qt lint:typoscript` executes without error when `typoscript-lint.yaml`
        is present in the project root
- [x] Verify all quality gates pass (AC: 8)
  - [x] Run `composer lint:composer`
  - [x] Run `composer lint:editorconfig`
  - [x] Run `composer lint:php`
  - [x] Run `composer lint:rector`
  - [x] Run `composer sca:php`
  - [x] Run `composer test`

## Dev Notes

### Root Cause

`ConfigurationHierarchy` (the sole source of truth for file discovery) maps only the
`.yml` extension for typoscript-lint:

```php
// src/Configuration/ConfigurationHierarchy.php

FILE_PATTERNS['tool_specific'] => [
    // ...
    'typoscript-lint.yml',   // only .yml -- .yaml is missing
],

TOOL_CONFIG_FILES = [
    // ...
    'typoscript-lint' => ['typoscript-lint.yml'],  // only .yml
];
```

When a developer creates `typoscript-lint.yaml`, the file is not matched by any entry
in `getExistingConfigurationFiles()`. The file is therefore invisible to the tool
runner's config path resolution.

The secondary symptom -- validation against `quality-tools.json` -- needs tracing
during implementation. A likely candidate is that `typoscript-lint.yaml` is picked up
by a YAML file scan that feeds into `ConfigurationValidator::validate()`, possibly in
`ConfigValidateRunner` or a path that invokes `ConfigurationLoader` with a broad glob.

### Fix Location

Primary fix -- two constant arrays in one file:

| File | Change |
|------|--------|
| `src/Configuration/ConfigurationHierarchy.php` | `FILE_PATTERNS['tool_specific']`: add `'typoscript-lint.yaml'` |
| `src/Configuration/ConfigurationHierarchy.php` | `FILE_PATTERNS['tool_config_dir']`: add `'config/typoscript-lint.yaml'` |
| `src/Configuration/ConfigurationHierarchy.php` | `TOOL_CONFIG_FILES['typoscript-lint']`: add `'typoscript-lint.yaml'` |

Secondary fix (if investigation confirms the secondary code path):
identify the guard condition that should restrict `ConfigurationValidator::validate()`
to quality-tools config files only.

### Existing Test Baseline

Run `composer test` before starting to confirm the baseline passes. The relevant
existing test class is `ConfigurationHierarchyTest` -- check its location with:

```bash
find tests/ -name "ConfigurationHierarchyTest.php"
```

### Pattern to Follow

The other tool runners (rector, phpstan, php-cs-fixer) follow the same pattern. PHPStan
registers two filenames (`phpstan.neon`, `phpstan.neon.dist`) as an example of multiple
variants for one tool.

### References

- Bug report: GL#7 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/7)
- Primary fix target: `src/Configuration/ConfigurationHierarchy.php`
- Config validator: `src/Configuration/ConfigurationValidator.php` (uses `quality-tools.json`)
- Tool config validator: `src/Configuration/Validator/TyposcriptLintConfigurationValidator.php`
- Sprint milestone: Epic 2 - Platform Stability & Defect Resolution

## Tracker References

- GitLab: [GL#7](https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/7)

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

Secondary code path traced: `ConfigurationDiscovery.loadConfigurationFile()` dispatches YAML
files to `YamlFileLoaderTrait.loadYamlFile()`, which validates all YAML content against the
quality-tools JSON schema. After the primary fix makes `typoscript-lint.yaml` visible to the
hierarchy, the file would be loaded as a generic YAML and schema-validated, causing a
validation error. Fixed by adding a guard in `loadConfigurationFile()` that routes
tool-specific YAML files through `loadToolConfigurationFile()` instead.

Pre-existing editorconfig issue (missing final newline) in two planning artifact files was
fixed as part of bringing all quality gates to a clean state. Pre-existing Rector warning
about deprecated skip rule was resolved by removing the unused rule and import.

### Completion Notes List

- Primary fix: Added `'typoscript-lint.yaml'` to three constants in `ConfigurationHierarchy`:
  `FILE_PATTERNS['tool_specific']`, `FILE_PATTERNS['tool_config_dir']` (as
  `'config/typoscript-lint.yaml'`), and `TOOL_CONFIG_FILES['typoscript-lint']`.
- Secondary fix: Added guard in `ConfigurationDiscovery.loadConfigurationFile()` to route
  tool-specific YAML files through `loadToolConfigurationFile()`, bypassing quality-tools
  schema validation.
- Updated `testToolConfigFileMappings` to expect the new `typoscript-lint.yaml` entry.
- Added two unit tests: `testTyposcriptLintYamlIsInFilePatterns` and
  `testTyposcriptLintYamlResolvesToTool`.
- Added integration test class `TyposcriptLintYamlConfigTest` with three tests covering
  discovery, schema-validation safety, and command execution.
- Created fixture `tests/Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml` and
  mock executable `tests/Fixtures/mockExecutables/typoscript-lint`.
- All 1157 tests pass (1152 baseline + 5 new); all five quality gates pass with zero errors.

### File List

- src/Configuration/ConfigurationHierarchy.php
- src/Configuration/ConfigurationDiscovery.php
- rector.php
- tests/Unit/Configuration/ConfigurationHierarchyTest.php
- tests/Integration/Configuration/TyposcriptLintYamlConfigTest.php
- tests/Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml
- tests/Fixtures/mockExecutables/typoscript-lint
- _bmad-output/implementation-artifacts/sprint-status.yaml
- _bmad-output/planning-artifacts/sprint-change-proposal-2026-05-19.md

### Change Log

- 2026-05-19: Implemented GL#7 fix -- typoscript-lint.yaml in project root is now recognized
  and used without triggering quality-tools schema validation errors. Added 5 tests. All
  quality gates pass.

### Review Findings
