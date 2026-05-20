# Deferred Work

## Deferred from: code review of 2-5-fix-typoscript-lint-yaml-config-overwrite-validation-error (2026-05-19)

- config/typoscript-lint.yaml (tool_config_dir level) has no end-to-end test -- no integration test places the file at config/ and verifies discovery and routing through loadConfigurationFile
- ConfigurationDiscovery::loadConfigurationFile guard has no dedicated unit test -- the secondary fix (routing tool-specific YAML away from schema validation) is covered only through integration tests
- TypoScriptLintRunner::DEFAULT_CONFIG_FILE hardcodes .yml -- pre-existing inconsistency with the new .yaml support; the fallback default path still uses the old extension
