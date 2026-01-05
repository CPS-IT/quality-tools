# Configuration Troubleshooting Guide

This guide helps you diagnose and fix common issues with the unified YAML configuration system.

## Quick Diagnosis Commands

When encountering configuration issues, start with these diagnostic commands:

```bash
# Check if configuration file exists and is found
vendor/bin/qt config:validate

# Show resolved configuration from all sources
vendor/bin/qt config:show --verbose

# Show configuration in JSON format for debugging
vendor/bin/qt config:show --format=json

# Test a specific tool with current configuration
vendor/bin/qt lint:phpstan --verbose
```

## Common Issues and Solutions

### 1. Configuration File Not Found

**Symptoms:**
- Warning: "No YAML configuration file found in project root"
- Tools use default settings instead of your configuration

**Diagnosis:**
```bash
# Check current directory
pwd
ls -la .quality-tools.yaml

# Check what the loader is looking for
vendor/bin/qt config:show --verbose
```

**Solutions:**

1. **Initialize configuration file:**
   ```bash
   vendor/bin/qt config:init --template=typo3-site-package
   ```

2. **Check file naming:**
   - Use `.quality-tools.yaml` (preferred)
   - Alternative: `quality-tools.yaml`
   - Alternative: `quality-tools.yml`

3. **Check file location:**
   ```bash
   # File must be in project root, not subdirectory
   mv config/.quality-tools.yaml ./.quality-tools.yaml
   ```

### 2. Configuration Validation Errors

**Symptoms:**
- Error: "Invalid configuration in .quality-tools.yaml"
- Validation fails with specific error messages

**Diagnosis:**
```bash
vendor/bin/qt config:validate
```

**Common validation errors and fixes:**

#### Invalid Tool Names
```yaml
# WRONG
quality-tools:
  tools:
    php-stan:  # Should be "phpstan"
      level: 6
```

```yaml
# CORRECT
quality-tools:
  tools:
    phpstan:   # Correct tool name
      level: 6
```

#### Type Mismatches
```yaml
# WRONG
quality-tools:
  tools:
    phpstan:
      level: "6"     # Should be integer, not string
      enabled: "true" # Should be boolean, not string
```

```yaml
# CORRECT
quality-tools:
  tools:
    phpstan:
      level: 6       # Integer
      enabled: true  # Boolean
```

#### Invalid Enum Values
```yaml
# WRONG
quality-tools:
  tools:
    rector:
      level: "typo3-14"  # Not a valid level

  output:
    verbosity: "loud"    # Not a valid verbosity level
```

```yaml
# CORRECT
quality-tools:
  tools:
    rector:
      level: "typo3-13"  # Valid: typo3-13, typo3-12, typo3-11

  output:
    verbosity: "verbose" # Valid: quiet, normal, verbose, debug
```

#### Range Violations
```yaml
# WRONG
quality-tools:
  tools:
    phpstan:
      level: 15  # Maximum is 9

  performance:
    max_processes: 50  # Maximum is 16
```

```yaml
# CORRECT
quality-tools:
  tools:
    phpstan:
      level: 8   # Range: 0-9

  performance:
    max_processes: 8   # Range: 1-16
```

### 3. Environment Variable Issues

**Symptoms:**
- Error: "Environment variable 'VAR_NAME' is not set and no default value provided"
- Variables not being substituted correctly

**Diagnosis:**
```bash
# Check environment variables
echo $PROJECT_NAME
echo $PHPSTAN_LEVEL

# Check variable resolution in config
vendor/bin/qt config:show | grep -A 5 "project:"
```

**Solutions:**

1. **Missing required variables:**
   ```bash
   # Error with ${PROJECT_NAME}
   export PROJECT_NAME="my-project"
   
   # Or add default in YAML
   name: "${PROJECT_NAME:-default-project}"
   ```

2. **Variable syntax issues:**
   ```yaml
   # WRONG
   name: "$PROJECT_NAME"         # Missing braces
   name: "${PROJECT_NAME}"       # Missing default, will fail if unset
   name: "${PROJECT-NAME:-def}"  # Hyphens not allowed in variable names
   
   # CORRECT
   name: "${PROJECT_NAME:-default}"  # Correct syntax with default
   ```

3. **Type conversion issues:**
   ```bash
   # Wrong: Boolean values
   export ENABLE_PARALLEL="True"    # Capital T won't work
   export ENABLE_PARALLEL="1"       # Will work (converted to true)
   export ENABLE_PARALLEL="true"    # Will work
   
   # Wrong: Numeric values
   export PHPSTAN_LEVEL="six"       # Won't work
   export PHPSTAN_LEVEL="6"         # Will work
   ```

### 4. YAML Syntax Errors

**Symptoms:**
- Error: "Configuration file must contain valid YAML data"
- Parsing errors with line numbers

**Common YAML syntax issues:**

#### Indentation Errors
```yaml
# WRONG - Inconsistent indentation
quality-tools:
  project:
    name: "my-project"
   php_version: "8.3"  # Wrong indentation

  tools:
     phpstan:  # Wrong indentation
      level: 6
```

```yaml
# CORRECT - Consistent 2-space indentation
quality-tools:
  project:
    name: "my-project"
    php_version: "8.3"

  tools:
    phpstan:
      level: 6
```

#### Quote Issues
```yaml
# WRONG
quality-tools:
  project:
    name: my project with spaces  # Needs quotes
    php_version: 8.3              # Should be string

  tools:
    rector:
      level: typo3-13  # Needs quotes due to hyphen
```

```yaml
# CORRECT
quality-tools:
  project:
    name: "my project with spaces"
    php_version: "8.3"

  tools:
    rector:
      level: "typo3-13"
```

#### Array Syntax Errors
```yaml
# WRONG
quality-tools:
  paths:
    scan: packages/, config/  # Not valid YAML array syntax
```

```yaml
# CORRECT
quality-tools:
  paths:
    scan:
      - "packages/"
      - "config/"
```

### 5. Tool Behavior Issues

**Symptoms:**
- Tools not using configuration settings
- Unexpected tool behavior
- Performance issues

**Diagnosis:**
```bash
# Check resolved configuration for specific tool
vendor/bin/qt config:show | grep -A 10 "phpstan:"

# Run tool with verbose output
vendor/bin/qt lint:phpstan --verbose

# Check if tool is enabled
vendor/bin/qt config:show | grep -A 1 "enabled:"
```

**Solutions:**

1. **Tool not using configuration:**
   ```bash
   # Verify tool is enabled
   vendor/bin/qt config:show | grep -A 5 "phpstan:"
   
   # Check for typos in tool names
   vendor/bin/qt config:validate
   ```

2. **Memory limit issues:**
   ```yaml
   quality-tools:
     tools:
       phpstan:
         memory_limit: "2G"  # Increase if analysis fails with memory errors
   ```

3. **Path configuration issues:**
   ```yaml
   quality-tools:
     paths:
       scan:
         - "packages/"     # Ensure paths exist
         - "src/"          # Check relative to project root
   ```

### 6. Performance Problems

**Symptoms:**
- Slow tool execution
- High memory usage
- System becoming unresponsive

**Diagnosis:**
```bash
# Check current performance settings
vendor/bin/qt config:show | grep -A 10 "performance:"

# Run with single process for debugging
vendor/bin/qt lint:phpstan --no-optimization
```

**Solutions:**

1. **Reduce parallelism:**
   ```yaml
   quality-tools:
     performance:
       parallel: false  # Disable for debugging
       max_processes: 2  # Reduce for systems with limited resources
   ```

2. **Adjust memory limits:**
   ```yaml
   quality-tools:
     tools:
       phpstan:
         memory_limit: "512M"  # Reduce for smaller projects
   ```

3. **Enable caching:**
   ```yaml
   quality-tools:
     performance:
       cache_enabled: true

     tools:
       php-cs-fixer:
         cache: true
   ```

### 7. Global vs Project Configuration Issues

**Symptoms:**
- Configuration not behaving as expected
- Settings being overridden unexpectedly

**Diagnosis:**
```bash
# Show all configuration sources
vendor/bin/qt config:show --verbose

# Check for global configuration
ls -la ~/.quality-tools.yaml
cat ~/.quality-tools.yaml
```

**Configuration Precedence:**
1. Package defaults (lowest)
2. Global user config (`~/.quality-tools.yaml`)
3. Project config (`.quality-tools.yaml`)
4. CLI overrides (highest)

**Solutions:**

1. **Check global configuration:**
   ```bash
   # View global config if exists
   cat ~/.quality-tools.yaml
   
   # Remove if causing conflicts
   mv ~/.quality-tools.yaml ~/.quality-tools.yaml.backup
   ```

2. **Override global settings in project:**
   ```yaml
   # Project .quality-tools.yaml
   quality-tools:
     tools:
       phpstan:
         level: 6  # Overrides global setting
   ```

## Debugging Workflows

### Complete Diagnostic Workflow

When facing configuration issues, follow this systematic approach:

```bash
# Step 1: Verify file exists and location
pwd
ls -la .quality-tools.*

# Step 2: Validate syntax and schema
vendor/bin/qt config:validate

# Step 3: Check resolved configuration
vendor/bin/qt config:show --verbose

# Step 4: Test environment variables
echo $PROJECT_NAME
echo $PHPSTAN_LEVEL

# Step 5: Test specific tool
vendor/bin/qt lint:phpstan --verbose

# Step 6: Check for conflicts
ls -la ~/.quality-tools.yaml
```

### Isolation Testing

Test configuration in isolation:

```bash
# Create minimal test configuration
cat > test-config.yaml << 'EOF'
quality-tools:
  project:
    name: "test-project"
  tools:
    phpstan:
      level: 6
EOF

# Test with specific config
vendor/bin/qt lint:phpstan --config=test-config.yaml
```

### Environment Variable Testing

```bash
# Test variable substitution
export TEST_PROJECT="test-value"
echo "quality-tools:
  project:
    name: \"\${TEST_PROJECT:-default}\"" > test-env-config.yaml

# Check resolution
vendor/bin/qt config:show --config=test-env-config.yaml
```

## Performance Troubleshooting

### Memory Issues

**Symptoms:**
- "Fatal error: Allowed memory size exhausted"
- Tools terminating unexpectedly

**Solutions:**
```yaml
quality-tools:
  tools:
    phpstan:
      memory_limit: "2G"  # Increase memory limit
    
  performance:
    parallel: false      # Disable parallel processing
```

### CPU/Process Issues

**Symptoms:**
- System becomes unresponsive
- Very slow tool execution

**Solutions:**
```yaml
quality-tools:
  performance:
    max_processes: 2     # Reduce concurrent processes
    parallel: false      # Disable for debugging
```

### Disk I/O Issues

**Symptoms:**
- Slow file scanning
- High disk usage

**Solutions:**
```yaml
quality-tools:
  paths:
    exclude:
      - "var/"
      - "vendor/"
      - "node_modules/"
      - ".git/"          # Add more exclusions
      - "public/"
  
  performance:
    cache_enabled: true   # Enable caching to reduce I/O
```

## Getting Help

### Information to Collect

When seeking help, collect this information:

```bash
# System information
php --version
composer --version

# Configuration information
vendor/bin/qt config:show --verbose > config-debug.txt

# Error information
vendor/bin/qt config:validate > validation-debug.txt 2>&1
vendor/bin/qt lint:phpstan --verbose > tool-debug.txt 2>&1
```

### Environment Information

```bash
# Environment variables
env | grep -E '(PROJECT_|PHPSTAN_|RECTOR_|PHP_|TYPO3_)' > env-debug.txt

# File permissions
ls -la .quality-tools.* > permissions-debug.txt

# Project structure
find . -name "*.yaml" -o -name "*.yml" | head -20 > yaml-files.txt
```

### Common Support Questions

1. **Configuration not working:**
   - Share output of `vendor/bin/qt config:show --verbose`
   - Share the exact error message
   - Share your `.quality-tools.yaml` file

2. **Tool behavior issues:**
   - Share output of `vendor/bin/qt config:validate`
   - Share tool output with `--verbose` flag
   - Describe expected vs actual behavior

3. **Performance problems:**
   - Share system specifications (CPU, RAM)
   - Share performance settings from config
   - Describe project size (number of files)

## Prevention Tips

### Configuration Best Practices

1. **Always validate after changes:**
   ```bash
   vendor/bin/qt config:validate
   ```

2. **Use version control:**
   ```bash
   git add .quality-tools.yaml
   git commit -m "Add quality tools configuration"
   ```

3. **Document custom settings:**
   ```yaml
   # .quality-tools.yaml
   # Custom configuration for legacy project
   # PHPStan level lowered due to legacy code
   quality-tools:
     tools:
       phpstan:
         level: 4  # Normally 6, but legacy code needs lower level
   ```

4. **Test in different environments:**
   ```bash
   # Test without environment variables
   unset PROJECT_NAME
   vendor/bin/qt config:show

   # Test with different values
   export PROJECT_NAME="test-project"
   vendor/bin/qt config:show
   ```

### Maintenance

1. **Regular validation:**
   ```bash
   # Add to CI pipeline
   vendor/bin/qt config:validate
   ```

2. **Keep backups:**
   ```bash
   cp .quality-tools.yaml .quality-tools.yaml.backup
   ```

3. **Monitor for updates:**
   - Check for new configuration options in package updates
   - Review and update templates when needed

This troubleshooting guide covers the most common issues you'll encounter with the YAML configuration system. Most problems stem from YAML syntax errors, environment variable issues, or configuration validation failures, all of which can be quickly diagnosed and fixed using the provided commands and solutions.