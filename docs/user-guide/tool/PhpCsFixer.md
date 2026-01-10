PHP CS Fixer
=============

PHP CS Fixer coding standards tool with automatic resource optimization.

## CLI Command Usage (Recommended)

### Lint Command (Analysis Only)

Use the CLI command to analyze code style issues with automatic optimization:

```shell
# Lint command with automatic optimization
$ vendor/bin/qt lint:php-cs-fixer
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHP CS Fixer memory limit to 460M for medium project
Optimization: Enabling parallel processing for improved performance

[PHP CS Fixer analysis follows...]
```

### Fix Command (Apply Changes)

Apply coding standards fixes with automatic optimization:

```shell
# Fix command with automatic optimization
$ vendor/bin/qt fix:php-cs-fixer
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHP CS Fixer memory limit to 460M for medium project
Optimization: Processing files with optimized memory allocation

[PHP CS Fixer fixes applied...]
```

**Key Benefits:**
- **Automatic Memory Management**: Dynamically calculates optimal memory limit (460M for medium projects)
- **Smart Path Scoping**: Analyzes `/packages` directory by default for TYPO3 projects
- **Memory Issue Resolution**: Prevents memory exhaustion on large codebases
- **Zero Configuration**: Works optimally without any setup

### CLI Options

```shell
# Basic analysis
vendor/bin/qt lint:php-cs-fixer

# Apply fixes
vendor/bin/qt fix:php-cs-fixer

# Custom path
vendor/bin/qt lint:php-cs-fixer --path=./custom/extension

# Custom memory limit (overrides automatic calculation)
vendor/bin/qt fix:php-cs-fixer --memory-limit=768M

# Disable automatic optimization
vendor/bin/qt lint:php-cs-fixer --no-optimization

# Optimization details shown by default
# (use --no-optimization to disable)
```

## Direct Tool Usage (Alternative)

### Default Configuration (Analysis Only)

For direct tool usage without optimization:

```shell
$ app/vendor/bin/php-cs-fixer fix --dry-run --config=app/vendor/cpsit/quality-tools/config/php-cs-fixer.php
```

**Note**: Direct usage does not include automatic optimization and may encounter memory issues on large projects.

### Apply Fixes (Direct Usage)

Apply fixes directly without optimization:

```shell
$ app/vendor/bin/php-cs-fixer fix --config=app/vendor/cpsit/quality-tools/config/php-cs-fixer.php
```

## Optimization Details

### Automatic Memory Allocation

PHP CS Fixer memory limits are automatically calculated based on project size:

| Project Size | File Range | Memory Limit | Typical Use Case |
|-------------|-----------|--------------|------------------|
| Small | < 100 files | 256M | Small extensions |
| Medium | 100-1000 files | 460M | Standard TYPO3 sites |
| Large | 1000-5000 files | 768M | Complex multi-site projects |
| Enterprise | > 5000 files | 1536M | Large enterprise installations |

### Performance Optimization

**Automatic Features:**
- **Memory Efficiency**: Prevents memory exhaustion during file processing
- **Smart Scoping**: Focuses on `/packages` directory for TYPO3 projects
- **Optimized Processing**: Uses efficient file processing for large codebases
- **Progress Indication**: Shows processing progress for operations with many files

### Example Output

**Small Project Analysis:**
```
Project Analysis: Found 45 files (12 PHP files) in /packages
Optimization: Setting PHP CS Fixer memory limit to 256M for small project
Optimization: Standard processing mode selected
Performance: Analysis completed in 3.2 seconds
```

**Large Project Processing:**
```
Project Analysis: Found 2,847 files (423 PHP files) in /packages
Optimization: Setting PHP CS Fixer memory limit to 768M for large project
Optimization: Enabling optimized file processing for performance
Performance: Fixed 127 files in 1m 43s (estimated 3m 20s without optimization)
Memory Usage: Peak 523M / 768M allocated
```

## Troubleshooting

### Memory Issues

If you still encounter memory issues with automatic optimization:

```shell
# Increase memory manually
vendor/bin/qt fix:php-cs-fixer --memory-limit=1024M

# Optimization details shown by default
# (use --no-optimization to disable)

# Disable optimization if needed
vendor/bin/qt fix:php-cs-fixer --no-optimization
```

### Performance Issues

```shell
# Verify optimization is working (details shown by default)
vendor/bin/qt lint:php-cs-fixer

# Should show project analysis and optimization decisions
# If not optimizing, check TYPO3 project detection
```

### Large File Processing

```shell
# For very large files, you may need more memory
vendor/bin/qt fix:php-cs-fixer --memory-limit=2048M

# Or process specific directories
vendor/bin/qt fix:php-cs-fixer --path=./packages/specific-extension
```

### Common Error Resolution

**"Allowed memory size exhausted" (Before Optimization):**
```
# This error is now prevented by automatic memory allocation
# But if you see it with --no-optimization, use:
vendor/bin/qt fix:php-cs-fixer --memory-limit=1024M
```

**"No files found to fix" (Path Issues):**
```
# Check if you're in the right directory (details shown by default)
vendor/bin/qt lint:php-cs-fixer

# Or specify custom path
vendor/bin/qt lint:php-cs-fixer --path=./src
```
