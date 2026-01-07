PHPStan
=======

PHPStan static analysis tool with automatic resource optimization.

## CLI Command Usage (Recommended)

### Automatic Optimization

The recommended way to use PHPStan is through the CLI command which provides automatic optimization:

```shell
# Lint command with automatic optimization
$ vendor/bin/qt lint:phpstan
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 552M for medium project
Optimization: Enabling parallel processing for improved performance

[PHPStan analysis follows...]
```

**Key Benefits:**
- **Automatic Memory Management**: Dynamically calculates optimal memory limit (552M for medium projects)
- **Smart Path Scoping**: Analyzes `/packages` directory by default for TYPO3 projects
- **Performance Optimization**: Enables parallel processing for large projects
- **Zero Configuration**: Works optimally without any setup

### CLI Options

```shell
# Basic usage
vendor/bin/qt lint:phpstan

# Custom analysis level
vendor/bin/qt lint:phpstan --level=8

# Custom memory limit (overrides automatic calculation)
vendor/bin/qt lint:phpstan --memory-limit=1024M

# Custom path
vendor/bin/qt lint:phpstan --path=./custom/extension

# Disable automatic optimization
vendor/bin/qt lint:phpstan --no-optimization

# Optimization details shown by default
# (use --no-optimization to disable)
```

## Direct Tool Usage (Alternative)

### Default Configuration

For direct tool usage without optimization, scan all PHP files in the packages directory:

```shell
$ app/vendor/bin/phpstan analyse -c app/vendor/cpsit/quality-tools/config/phpstan.neon
```

**Note**: Direct usage does not include automatic optimization and may encounter memory issues on large projects.

### Custom Path (Direct Usage)

Scan specific directory with direct tool usage:
```shell
$ app/vendor/bin/phpstan analyse -c phpstan.neon \
  ./<path to extension>
```

### Custom Level (Direct Usage)

Use custom analysis level with direct tool usage:
```shell
$ app/vendor/bin/phpstan analyse \
  -c app/vendor/cpsit/quality-tools/config/phpstan.neon \
  --level=2
```

## Optimization Details

### Automatic Memory Allocation

PHPStan memory limits are automatically calculated based on project size:

| Project Size | File Range | Memory Limit | Typical Use Case |
|-------------|-----------|--------------|------------------|
| Small | < 100 files | 256M | Small extensions |
| Medium | 100-1000 files | 552M | Standard TYPO3 sites |
| Large | 1000-5000 files | 1024M | Complex multi-site projects |
| Enterprise | > 5000 files | 2048M | Large enterprise installations |

### Performance Optimization

**Automatic Features:**
- **Parallel Processing**: Enabled for projects with 500+ files
- **Memory Efficiency**: Prevents memory exhaustion on large codebases
- **Smart Scoping**: Focuses on `/packages` directory for TYPO3 projects
- **Progress Indication**: Shows analysis progress for long-running operations

### Example Output

**Small Project:**
```
Project Analysis: Found 45 files (12 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 256M for small project
Optimization: Standard processing mode selected
Performance: Analysis completed in 8.2 seconds
```

**Large Project:**
```
Project Analysis: Found 2,847 files (423 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 1024M for large project
Optimization: Enabling parallel processing for improved performance
Performance: Analysis completed in 2m 15s (estimated 4m 30s without optimization)
Memory Usage: Peak 687M / 1024M allocated
```

## Troubleshooting

### Memory Issues

If you still encounter memory issues with automatic optimization:

```shell
# Increase memory manually
vendor/bin/qt lint:phpstan --memory-limit=2048M

# Optimization details shown by default
# (use --no-optimization to disable)

# Disable optimization if needed
vendor/bin/qt lint:phpstan --no-optimization
```

### Performance Issues

```shell
# Verify optimization is working (details shown by default)
vendor/bin/qt lint:phpstan

# Should show project analysis and optimization decisions
# If not optimizing, check TYPO3 project detection
```

### Analysis Level Issues

```shell
# Start with lower level for large projects
vendor/bin/qt lint:phpstan --level=2

# Gradually increase level as issues are resolved
vendor/bin/qt lint:phpstan --level=6  # Default level
vendor/bin/qt lint:phpstan --level=8  # Strict analysis
```
