# Test Fixtures for Issue 022: Configuration Override Feature

Manual testing scenarios for configuration file override and auto-discovery.
All paths in config files are relative to their installed location under
the project root. Run all commands from the project root.

## Usage

Copy the `.quality-tools.yaml` (and any tool config files) from a scenario
directory into the project root, then run the test commands.

### Scenario 1: Custom Config File via YAML

**Pre-conditions:** `.quality-tools.yaml` with explicit `config_file`, custom rector at `custom/rector.php`.

**Expected:** config:show displays `config_file: custom/rector.php`, rector uses it.

```bash
cp tmp/022-testing-plan/scenario-1/.quality-tools.yaml .
cp -r tmp/022-testing-plan/scenario-1/custom .

app/vendor/bin/qt config:show -v
app/vendor/bin/qt lint:rector -vv
```

### Scenario 2: Auto-Discovery of Tool Configs

**Pre-conditions:** `rector.php` in project root, no `config_file` in YAML.

**Expected:** Rector auto-discovers and uses the project root `rector.php`.

```bash
cp tmp/022-testing-plan/scenario-2/.quality-tools.yaml .
cp tmp/022-testing-plan/scenario-2/rector.php .

app/vendor/bin/qt config:show -v
app/vendor/bin/qt lint:rector -vv
```

### Scenario 3: Command Line Override

**Pre-conditions:** Custom config at `alternative/rector.php`.

**Expected:** CLI `--config` overrides YAML and auto-discovered configs.

```bash
cp tmp/022-testing-plan/scenario-3/.quality-tools.yaml .
cp -r tmp/022-testing-plan/scenario-3/alternative .

app/vendor/bin/qt lint:rector --config=alternative/rector.php -vv
```

### Scenario 4: Configuration Precedence

**Pre-conditions:** `rector.php` in root (auto-discoverable), YAML with `config_file: custom/rector.php`, alternative at `cli/rector.php`.

**Expected precedence:** CLI --config > YAML config_file > auto-discovered > package defaults.

```bash
cp tmp/022-testing-plan/scenario-4/.quality-tools.yaml .
cp tmp/022-testing-plan/scenario-4/rector.php .
cp -r tmp/022-testing-plan/scenario-4/custom .
cp -r tmp/022-testing-plan/scenario-4/cli .

# Step 1: Uses YAML config_file (custom/rector.php)
app/vendor/bin/qt lint:rector -vv
# Step 2: CLI override wins (cli/rector.php)
app/vendor/bin/qt lint:rector --config=cli/rector.php -vv
```

### Scenario 5: Multiple Tool Configurations

**Pre-conditions:** Custom configs for rector, phpstan, and php-cs-fixer.

**Expected:** Each tool uses its own custom config. Validation passes. JSON output includes all config_file properties.

```bash
cp tmp/022-testing-plan/scenario-5/.quality-tools.yaml .
cp -r tmp/022-testing-plan/scenario-5/config .
cp tmp/022-testing-plan/scenario-5/.php-cs-fixer.custom.php .

app/vendor/bin/qt config:validate
app/vendor/bin/qt config:show --format=json
app/vendor/bin/qt lint:rector -vv
app/vendor/bin/qt lint:phpstan -vv
app/vendor/bin/qt lint:php-cs-fixer -vv
```

### Scenario 6: Invalid Config File Path

**Pre-conditions:** `config_file` points to a non-existent path.

**Expected:** config:validate succeeds but warns about the missing file and fallback. lint:rector falls back to package defaults.

```bash
cp tmp/022-testing-plan/scenario-6/.quality-tools.yaml .

app/vendor/bin/qt config:validate
app/vendor/bin/qt lint:rector -vv
```

### Scenario 7: Multiple Invalid Config File Paths with Fallback

**Pre-conditions:** Multiple tools with `config_file` pointing to non-existent paths.

**Expected:** config:validate succeeds with warnings for each missing file and notes that package defaults will be used. Tool commands fall back to package defaults.

```bash
cp tmp/022-testing-plan/scenario-7/.quality-tools.yaml .

app/vendor/bin/qt config:validate
app/vendor/bin/qt lint:rector -vv
app/vendor/bin/qt lint:phpstan -vv
```

## Verification Commands

```bash
# Show resolved configuration with sources
app/vendor/bin/qt config:show -v

# Validate configuration
app/vendor/bin/qt config:validate

# Show as JSON for parsing
app/vendor/bin/qt config:show --format=json | jq '.["quality-tools"].tools.rector.config_file'

# Check which config file was actually used (verbose output)
app/vendor/bin/qt lint:rector -vvv 2>&1 | grep -i "config"
```

## Success Criteria

- Custom config files are used when specified in YAML
- Auto-discovery works when no custom config is set
- Command-line `--config` overrides take highest precedence
- Invalid config_file paths produce warnings (not failures) with fallback info
- All tools respect their config_file settings
- Clear error messages for invalid configurations

## Cleanup

After testing each scenario, remove the copied files:

```bash
rm -f .quality-tools.yaml rector.php .php-cs-fixer.custom.php
rm -rf custom/ alternative/ cli/ config/custom/
```
