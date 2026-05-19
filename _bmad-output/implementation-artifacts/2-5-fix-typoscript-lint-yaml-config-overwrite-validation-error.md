# Story 2.5: Fix typoscript-lint.yaml config overwrite validation error (GL#7)

Status: ready-for-dev

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

- [ ] Add `.yaml` extension to `ConfigurationHierarchy` constants (AC: 1, 2, 3)
  - [ ] Add `'typoscript-lint.yaml'` to `FILE_PATTERNS['tool_specific']`
  - [ ] Add `'config/typoscript-lint.yaml'` to `FILE_PATTERNS['tool_config_dir']`
  - [ ] Add `'typoscript-lint.yaml'` to `TOOL_CONFIG_FILES['typoscript-lint']`
- [ ] Investigate and fix the secondary path that routes unrecognized YAML files to
      `ConfigurationValidator` (AC: 2, 3)
  - [ ] Trace why `typoscript-lint.yaml` (before the fix) reaches `ConfigurationValidator`
        rather than failing silently; identify the code path
  - [ ] Apply a guard if needed so only known quality-tools config filenames are validated
        against the quality-tools JSON schema
- [ ] Add unit tests (AC: 5, 7)
  - [ ] `ConfigurationHierarchyTest`: assert `typoscript-lint.yaml` resolves to tool
        `typoscript-lint` via `getToolForConfigFile`
  - [ ] `ConfigurationHierarchyTest`: assert `typoscript-lint.yaml` appears in
        `FILE_PATTERNS['tool_specific']` and `TOOL_CONFIG_FILES['typoscript-lint']`
- [ ] Add integration test (AC: 6, 7)
  - [ ] Verify `qt lint:typoscript` executes without error when `typoscript-lint.yaml`
        is present in the project root
- [ ] Verify all quality gates pass (AC: 8)
  - [ ] Run `composer lint:composer`
  - [ ] Run `composer lint:editorconfig`
  - [ ] Run `composer lint:php`
  - [ ] Run `composer lint:rector`
  - [ ] Run `composer sca:php`
  - [ ] Run `composer test`

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

### Debug Log References

### Completion Notes List

### File List

### Review Findings