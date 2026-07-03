# Issue 026: typoscript-lint.yaml Extension Not Recognized as Tool Config Override

- **Status:** Open
- **GitLab:** GL#7 (https://gitlab.321.works/DevOps/testing/quality-tools/-/work_items/7)
- **GitHub:** none (GitLab-only, filed by internal user)
- **Reporter:** i.dirscherl (2026-04-14)

## Problem Summary

When a user places a file named `typoscript-lint.yaml` (with `.yaml` extension) in the project root to override the default typoscript-lint configuration, the file is not recognized as a tool-specific config override. Instead, it is picked up as a general quality-tools YAML config and validated against the quality-tools JSON schema, which fails because it is a typoscript-lint config, not a quality-tools config.

Workaround: rename the file to `.typoscript-lint.yml` and reference it explicitly via `config_file` in `.quality-tools.yaml`.

## Root Cause

`ConfigurationHierarchy::TOOL_CONFIG_FILES` and `FILE_PATTERNS` only register `typoscript-lint.yml`:

```php
// src/Configuration/ConfigurationHierarchy.php
'typoscript-lint' => ['typoscript-lint.yml'],
```

And in `FILE_PATTERNS`:
```php
'tool_specific' => [
    // ...
    'typoscript-lint.yml',
],
'tool_config_dir' => [
    // ...
    'config/typoscript-lint.yml',
],
```

The `.yaml` extension variant is absent from both. Since `.yaml` files are treated as general quality-tools configs, a `typoscript-lint.yaml` at project root is loaded as a quality-tools config and validated against the schema, producing a confusing error.

## Expected Behavior

`typoscript-lint.yaml` (and `config/typoscript-lint.yaml`) should be recognized as tool-specific config file overrides for the `typoscript-lint` tool, identical to how `typoscript-lint.yml` is handled.

## Fix

Add `.yaml` variants to both `TOOL_CONFIG_FILES` and `FILE_PATTERNS`:

```php
'typoscript-lint' => ['typoscript-lint.yml', 'typoscript-lint.yaml'],
```

And in `FILE_PATTERNS`:
```php
'tool_specific' => [
    // ...
    'typoscript-lint.yml',
    'typoscript-lint.yaml',
],
'tool_config_dir' => [
    // ...
    'config/typoscript-lint.yml',
    'config/typoscript-lint.yaml',
],
```

## Acceptance Criteria

- [ ] `typoscript-lint.yaml` in the project root is recognized as a typoscript-lint config override
- [ ] `typoscript-lint.yaml` in the `config/` directory is recognized as a typoscript-lint config override
- [ ] `qt config:validate` does not report schema errors when `typoscript-lint.yaml` is present
- [ ] `qt lint:typoscript` uses the custom `typoscript-lint.yaml` when present
- [ ] Unit test added for both `.yml` and `.yaml` extension recognition
- [ ] No regression on existing `.yml`-based overrides

## Related

- Issue 022: Configuration File Replacement Schema Validation (resolved) - original schema keys bug
- Feature 015: Configuration Overwrites (resolved) - hierarchical config system
