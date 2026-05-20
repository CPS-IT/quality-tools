# Deferred Work

Items surfaced during reviews that are real but not actionable in their originating
story. Each entry notes where it came from.

## Deferred from: code review of story 2-2-fix-pathresolutionservice-nested-structure-bug-issue-025 (2026-06-06)

- tools.<tool>.paths with an `additional`/`exclude` sub-key but no `scan` key now returns `[]` from `getToolPaths()`, so those configured paths are silently ignored. The schema permits this shape (`scan` is optional). No test covers "paths present, scan absent" -- the case where the new `['scan'] ?? []` diverges most from the old code. Explicitly scoped to Story 6.2 per the story's Dev Notes. [src/Service/PathResolutionService.php:78]
- A non-list `scan` value (e.g. a string) reaching `getToolPaths()` via the deferred-validation `Configuration` path would trigger string-offset access. Pre-existing concern of the unvalidated config path; the validated load path rejects a string `scan` at schema validation. [src/Service/PathResolutionService.php:78]
- An explicit `scan: []` override silently falls back to the global PathScanner rather than scanning nothing. This is the pre-existing `!empty($toolPaths)` semantics of `getResolvedPathsForTool()`, unchanged by this fix; flagged for a future design decision on whether explicit-empty should mean "no override" or "scan nothing". [src/Service/PathResolutionService.php:59]
- No unit test exercises an unknown/absent tool name or a second tool name; only `rector`/`phpstan` are tested, so an implementation that ignored the `$tool` argument would still pass. Minor coverage gap beyond AC3's required valid/empty/missing-key cases. [tests/Unit/Service/PathResolutionServiceTest.php:416]

## Deferred from: code review of 2-5-fix-typoscript-lint-yaml-config-overwrite-validation-error (2026-05-19)

- config/typoscript-lint.yaml (tool_config_dir level) has no end-to-end test -- no integration test places the file at config/ and verifies discovery and routing through loadConfigurationFile
- ConfigurationDiscovery::loadConfigurationFile guard has no dedicated unit test -- the secondary fix (routing tool-specific YAML away from schema validation) is covered only through integration tests
- TypoScriptLintRunner::DEFAULT_CONFIG_FILE hardcodes .yml -- pre-existing inconsistency with the new .yaml support; the fallback default path still uses the old extension
