# Issue 022: Manual Testing Plan - Configuration Override Feature

## Test Scenarios

### Scenario 1: Custom Config File via YAML
**Pre-conditions:**
- Project with `.quality-tools.yaml`
- Custom rector configuration file at `custom/rector.php`

**Configuration:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      enabled: true
      config_file: "custom/rector.php"
```

**Steps:**
1. Run `vendor/bin/qt config:show -v`
2. Run `vendor/bin/qt lint:rector`

**Expected:**
- config:show displays `config_file: custom/rector.php`
- Rector uses custom/rector.php instead of default configuration

---

### Scenario 2: Auto-Discovery of Tool Configs
**Pre-conditions:**
- Project root contains `rector.php`
- No `config_file` set in `.quality-tools.yaml`

**Configuration:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      enabled: true
```

**Steps:**
1. Run `vendor/bin/qt config:show -v`
2. Run `vendor/bin/qt lint:rector`

**Expected:**
- config:show shows auto-discovered rector.php in verbose output
- Rector uses project root rector.php automatically

---

### Scenario 3: Command Line Override
**Pre-conditions:**
- Project with default configuration
- Custom config at `alternative/rector.php`

**Steps:**
1. Run `vendor/bin/qt lint:rector --config=alternative/rector.php`

**Expected:**
- Uses alternative/rector.php regardless of YAML or auto-discovered configs

---

### Scenario 4: Configuration Precedence
**Pre-conditions:**
- `rector.php` in project root (auto-discoverable)
- `.quality-tools.yaml` with `config_file: "custom/rector.php"`
- Alternative config at `cli/rector.php`

**Configuration:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      config_file: "custom/rector.php"
```

**Steps:**
1. Run `vendor/bin/qt lint:rector` (uses config_file from YAML)
2. Run `vendor/bin/qt lint:rector --config=cli/rector.php` (CLI override)

**Expected Precedence:**
1. CLI `--config` option wins (uses cli/rector.php)
2. YAML `config_file` used when no CLI option (uses custom/rector.php)
3. Auto-discovered file ignored when config_file is set

---

### Scenario 5: Multiple Tool Configurations
**Pre-conditions:**
- Custom configs for multiple tools

**Configuration:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      config_file: "config/custom/rector.php"
    phpstan:
      config_file: "config/custom/phpstan.neon"
    php-cs-fixer:
      config_file: ".php-cs-fixer.custom.php"
```

**Steps:**
1. Run `vendor/bin/qt config:validate`
2. Run `vendor/bin/qt config:show --format=json`
3. Run each tool command

**Expected:**
- Validation passes
- Each tool uses its specified custom configuration
- JSON output includes all config_file properties

---

### Scenario 6: Invalid Config File Path
**Pre-conditions:**
- Config file path that doesn't exist

**Configuration:**
```yaml
# .quality-tools.yaml
quality-tools:
  tools:
    rector:
      config_file: "non-existent/rector.php"
```

**Steps:**
1. Run `vendor/bin/qt config:validate`
2. Run `vendor/bin/qt lint:rector`

**Expected:**
- config:validate reports warning or error
- Tool command fails with clear error message about missing config file

---

## Verification Commands

### Check Current Configuration
```bash
# Show resolved configuration with sources
vendor/bin/qt config:show -v

# Validate configuration
vendor/bin/qt config:validate

# Show as JSON for parsing
vendor/bin/qt config:show --format=json | jq '.["quality-tools"].tools.rector.config_file'
```

### Test Tool Execution
```bash
# Dry run with verbose output
vendor/bin/qt lint:rector -v

# Check which config file was actually used
vendor/bin/qt lint:rector -vvv 2>&1 | grep -i "config"
```

## Success Criteria
- Custom config files are used when specified
- Auto-discovery works when no custom config is set
- Command-line overrides take precedence
- Clear error messages for invalid configurations
- All tools respect their config_file settings