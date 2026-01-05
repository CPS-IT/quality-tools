# Migration Guide

This guide helps you migrate from individual tool-specific configurations to the unified YAML configuration system.

## Overview

The unified YAML configuration system is fully backward compatible. Your existing tool-specific configuration files will continue to work while you gradually migrate to the new system.

**Migration Benefits:**
- Single configuration file instead of multiple tool configs
- Environment variable support
- JSON Schema validation
- Configuration hierarchy and merging
- Better maintainability and consistency

## Migration Strategy

### Recommended Approach: Gradual Migration

1. **Keep existing configs** - Everything continues to work
2. **Initialize YAML config** - Start with a template
3. **Migrate one tool at a time** - Test each step
4. **Remove old configs** - Clean up when fully migrated

### Alternative Approach: Fresh Start

1. **Backup existing configs** - Save your current setup
2. **Initialize new YAML config** - Use appropriate template
3. **Customize all tools** - Set up everything at once
4. **Test thoroughly** - Verify all tools work as expected

## Tool-Specific Migration

### Rector Migration

**Before (rector.php):**
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/config/system',
    ])
    ->withSkip([
        __DIR__ . '/packages/*/Resources/Private/Libs/*',
    ])
    ->withSets([
        Typo3SetList::TYPO3_13,
    ])
    ->withConfiguredRule(
        Typo3Option::TYPO3_VERSION_CONSTRAINT,
        '13.4'
    )
    ->withPhpVersion(PhpVersion::PHP_83);
```

**After (YAML):**
```yaml
quality-tools:
  project:
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/system/"
    exclude:
      - "packages/*/Resources/Private/Libs/*"

  tools:
    rector:
      enabled: true
      level: "typo3-13"
```

### PHPStan Migration

**Before (phpstan.neon):**
```neon
includes:
    - vendor/saschaegerer/phpstan-typo3/extension.neon

parameters:
    level: 6
    paths:
        - packages
        - config/system
    excludePaths:
        - packages/*/Resources/Private/Libs/*
        - packages/*/Tests/Acceptance/*
        - var/*
        - vendor/*
    
    ignoreErrors:
        - '#Call to an undefined method.*#'
    
    memory_limit: 1G
```

**After (YAML):**
```yaml
quality-tools:
  paths:
    scan:
      - "packages/"
      - "config/system/"
    exclude:
      - "packages/*/Resources/Private/Libs/*"
      - "packages/*/Tests/Acceptance/*"
      - "var/"
      - "vendor/"

  tools:
    phpstan:
      enabled: true
      level: 6
      memory_limit: "1G"
```

### PHP CS Fixer Migration

**Before (.php-cs-fixer.php):**
```php
<?php

$config = new PhpCsFixer\Config();
return $config
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/packages')
            ->in(__DIR__ . '/config/system')
            ->exclude([
                'Resources/Private/Libs',
                'Documentation',
            ])
    )
    ->setRules([
        '@TYPO3' => true,
        'declare_strict_types' => true,
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
```

**After (YAML):**
```yaml
quality-tools:
  paths:
    scan:
      - "packages/"
      - "config/system/"
    exclude:
      - "*/Resources/Private/Libs/*"
      - "*/Documentation/*"

  tools:
    php-cs-fixer:
      enabled: true
      preset: "typo3"
      cache: true
```

### Fractor Migration

**Before (fractor.php):**
```php
<?php

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\Fractor\Set\Typo3SetList;

return FractorConfiguration::configure()
    ->withSets([
        Typo3SetList::TYPO3_RECTOR_FRACTOR,
    ])
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/config/sites',
    ])
    ->withConfiguredRule(
        \a9f\Fractor\ValueObject\Indent::class,
        2
    );
```

**After (YAML):**
```yaml
quality-tools:
  paths:
    scan:
      - "packages/"
      - "config/sites/"

  tools:
    fractor:
      enabled: true
      indentation: 2
```

### TypoScript Lint Migration

**Before (typoscript-lint.yml):**
```yaml
paths:
  - packages/*/Configuration/TypoScript/
  - packages/*/Resources/Private/TypoScript/
  - config/sites/*/

sniffs:
  - class: Helmich\TypoScriptLint\Linter\Sniff\IndentationSniff
    parameters:
      indentPerLevel: 2
  - class: Helmich\TypoScriptLint\Linter\Sniff\DeadCodeSniff
  - class: Helmich\TypoScriptLint\Linter\Sniff\OperatorWhitespaceSniff
  - class: Helmich\TypoScriptLint\Linter\Sniff\DuplicateAssignmentSniff

filePatterns:
  - "*.typoscript"
  - "*.ts"
  - "*.txt"
```

**After (YAML):**
```yaml
quality-tools:
  paths:
    scan:
      - "packages/"  # Will auto-discover TypoScript files
      - "config/sites/"

  tools:
    typoscript-lint:
      enabled: true
      indentation: 2
```

## Complete Migration Examples

### Example 1: TYPO3 Extension

**Before: Multiple config files**
- `rector.php` (85 lines)
- `phpstan.neon` (32 lines)
- `.php-cs-fixer.php` (28 lines)
- `typoscript-lint.yml` (18 lines)

**After: Single YAML file**
```yaml
# .quality-tools.yaml
quality-tools:
  project:
    name: "my-extension"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "Classes/"
      - "Configuration/"
      - "Tests/"
    exclude:
      - "vendor/"
      - ".build/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    phpstan:
      enabled: true
      level: 8
      memory_limit: "512M"

    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  performance:
    parallel: false  # Better for smaller projects
```

### Example 2: Site Package

**Before: Multiple config files with custom paths**
- Custom Rector configuration with site-specific paths
- PHPStan configuration for both PHP and TypoScript
- Custom CS Fixer rules for project standards

**After: Unified configuration**
```yaml
# .quality-tools.yaml
quality-tools:
  project:
    name: "client-site-package"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/"
    exclude:
      - "var/"
      - "public/"
      - "node_modules/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    fractor:
      enabled: true
      indentation: 2

    phpstan:
      enabled: true
      level: 6
      memory_limit: "1G"

    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  performance:
    parallel: true
    max_processes: 4
```

## Step-by-Step Migration Process

### Step 1: Backup Current Configuration

```bash
# Create backup directory
mkdir config-backup

# Backup existing configs
cp rector.php config-backup/
cp phpstan.neon config-backup/
cp .php-cs-fixer.php config-backup/
cp typoscript-lint.yml config-backup/
```

### Step 2: Initialize YAML Configuration

```bash
# Choose appropriate template
vendor/bin/qt config:init --template=typo3-site-package

# Or initialize with custom template
vendor/bin/qt config:init --template=typo3-extension
```

### Step 3: Test Each Tool

Test tools one by one to ensure they work correctly:

```bash
# Test Rector
vendor/bin/qt lint:rector --dry-run

# Test PHPStan
vendor/bin/qt lint:phpstan

# Test PHP CS Fixer
vendor/bin/qt lint:php-cs-fixer

# Test Fractor
vendor/bin/qt lint:fractor

# Test TypoScript Lint
vendor/bin/qt lint:typoscript
```

### Step 4: Compare Results

Compare the output of tools using the old and new configurations:

```bash
# Old way
app/vendor/bin/rector -c rector.php --dry-run

# New way
vendor/bin/qt lint:rector

# Compare outputs to ensure consistency
```

### Step 5: Migrate Custom Rules

If you have custom rules or complex configurations, you may need to:

1. **Keep tool-specific configs for complex rules**
2. **Use the `--config` option to specify custom configs**
3. **Contribute missing features to the unified system**

```bash
# Use custom config when needed
vendor/bin/qt lint:rector --config=custom-rector.php
```

### Step 6: Remove Old Configuration Files

Once you're confident the migration is complete:

```bash
# Remove old configuration files
rm rector.php
rm phpstan.neon
rm .php-cs-fixer.php
rm typoscript-lint.yml

# Keep backups for a while
# rm -rf config-backup  # Do this later
```

## Handling Complex Scenarios

### Custom Rector Rules

If you have custom Rector rules that aren't covered by the TYPO3 sets:

**Option 1: Keep hybrid approach**
```yaml
# .quality-tools.yaml - for standard tools
quality-tools:
  tools:
    phpstan:
      enabled: true
    php-cs-fixer:
      enabled: true
```

```bash
# Use custom Rector config
vendor/bin/qt lint:rector --config=custom-rector.php
```

**Option 2: Use command-line overrides**
```bash
# Override config path
vendor/bin/qt lint:rector --config=path/to/custom/rector.php
```

### Custom PHPStan Extensions

For PHPStan extensions that require complex configuration:

```yaml
quality-tools:
  tools:
    phpstan:
      enabled: true
      level: 6
      # Basic config in YAML
```

Keep a minimal `phpstan.neon` for extensions:
```neon
# phpstan.neon - only for extensions
includes:
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-symfony/extension.neon

parameters:
    # Extension-specific parameters only
    doctrine:
        repositoryClass: App\Repository\BaseRepository
```

### Multi-Environment Configuration

Use environment variables for different environments:

```yaml
# .quality-tools.yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-my-project}"

  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"
      memory_limit: "${PHPSTAN_MEMORY:-1G}"

  output:
    colors: "${ENABLE_COLORS:-true}"

  performance:
    parallel: "${ENABLE_PARALLEL:-true}"
```

**Environment files:**

`.env.development`:
```bash
PROJECT_NAME="my-project-dev"
PHPSTAN_LEVEL="5"
PHPSTAN_MEMORY="512M"
ENABLE_PARALLEL="false"
```

`.env.ci`:
```bash
PROJECT_NAME="my-project-ci"
PHPSTAN_LEVEL="8"
PHPSTAN_MEMORY="2G"
ENABLE_COLORS="false"
```

## Troubleshooting Migration

### Common Issues

1. **Tools behave differently**
   - Compare configurations carefully
   - Check path differences
   - Verify tool versions

2. **Performance differences**
   - Adjust parallel processing settings
   - Configure memory limits appropriately
   - Enable/disable caching as needed

3. **Missing custom rules**
   - Keep tool-specific configs for complex rules
   - Use `--config` option for custom configurations
   - Gradually migrate custom rules

### Debugging Steps

1. **Validate new configuration**
   ```bash
   vendor/bin/qt config:validate
   ```

2. **Compare resolved configuration**
   ```bash
   vendor/bin/qt config:show --verbose
   ```

3. **Test with increased verbosity**
   ```bash
   vendor/bin/qt lint:phpstan --verbose
   ```

4. **Use old and new side by side**
   ```bash
   # Old way
   app/vendor/bin/phpstan analyse -c phpstan.neon packages/
   
   # New way
   vendor/bin/qt lint:phpstan
   
   # Compare outputs
   ```

## Migration Checklist

Use this checklist to ensure a complete migration:

- [ ] Backup existing configuration files
- [ ] Initialize YAML configuration with appropriate template
- [ ] Test each tool individually
- [ ] Compare results with previous configuration
- [ ] Handle custom rules and complex scenarios
- [ ] Set up environment-specific configurations
- [ ] Update CI/CD pipelines
- [ ] Update documentation
- [ ] Train team on new configuration system
- [ ] Remove old configuration files (after testing period)

## Getting Help

If you encounter issues during migration:

1. Check the [Configuration Reference](reference.md) for all available options
2. Review the [YAML Configuration Guide](yaml-configuration.md) for examples
3. Use `vendor/bin/qt config:validate` and `vendor/bin/qt config:show` for debugging
4. Compare old and new tool outputs to identify differences
5. Keep hybrid approach for complex scenarios that can't be easily migrated

The migration process is designed to be gradual and low-risk. Take your time and test thoroughly at each step.