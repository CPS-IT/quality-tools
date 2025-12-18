TYPO3 Rector
=============

TYPO3 Rector automated code modernization tool with automatic resource optimization.

## CLI Command Usage (Recommended)

### Lint Command (Analysis Only)

Use the CLI command to analyze modernization opportunities with automatic optimization:

```shell
# Lint command with automatic optimization
$ vendor/bin/qt lint:rector
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting Rector memory limit to 690M for medium project
Optimization: Enabling parallel processing and caching for improved performance

[Rector analysis follows...]
```

### Fix Command (Apply Changes)

Apply automated code modernization with automatic optimization:

```shell
# Fix command with automatic optimization
$ vendor/bin/qt fix:rector
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting Rector memory limit to 690M for medium project
Optimization: Enabling caching and optimized processing for performance

[Rector fixes applied...]
```

**Key Benefits:**
- **Automatic Memory Management**: Dynamically calculates optimal memory limit (690M for medium projects)
- **Performance Optimization**: 50%+ improvement on large projects through caching and parallel processing
- **Smart Path Scoping**: Analyzes `/packages` directory by default for TYPO3 projects
- **Memory Issue Resolution**: Prevents memory exhaustion during code transformation
- **Zero Configuration**: Works optimally without any setup

### CLI Options

```shell
# Basic analysis
vendor/bin/qt lint:rector

# Apply fixes
vendor/bin/qt fix:rector

# Custom path
vendor/bin/qt lint:rector --path=./custom/extension

# Custom memory limit (overrides automatic calculation)
vendor/bin/qt fix:rector --memory-limit=1024M

# Disable automatic optimization
vendor/bin/qt lint:rector --no-optimization

# View optimization decisions
vendor/bin/qt lint:rector --show-optimization
```

## Direct Tool Usage (Alternative)

### Default Configuration (Analysis Only)

For direct tool usage without optimization:

```shell
$ app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php --dry-run
```

**Note**: Direct usage does not include automatic optimization and may encounter memory issues on large projects.

### Apply Fixes (Direct Usage)

Apply fixes directly without optimization:

```shell
$ app/vendor/bin/rector -c app/vendor/cpsit/quality-tools/config/rector.php
```

## Optimization Details

### Automatic Memory Allocation

Rector memory limits are automatically calculated based on project size:

| Project Size | File Range | Memory Limit | Typical Use Case |
|-------------|-----------|--------------|------------------|
| Small | < 100 files | 384M | Small extensions |
| Medium | 100-1000 files | 690M | Standard TYPO3 sites |
| Large | 1000-5000 files | 1200M | Complex multi-site projects |
| Enterprise | > 5000 files | 2048M | Large enterprise installations |

**Note**: Rector requires the highest memory allocation among all tools due to the complexity of code transformation operations.

### Performance Optimization

**Automatic Features:**
- **Caching**: Enabled automatically to speed up repeated runs
- **Parallel Processing**: Enabled for projects with 500+ files
- **Memory Efficiency**: Prevents memory exhaustion during AST transformations
- **Smart Scoping**: Focuses on `/packages` directory for TYPO3 projects
- **Progress Indication**: Shows transformation progress for long-running operations

### Example Output

**Small Project Analysis:**
```
Project Analysis: Found 45 files (12 PHP files) in /packages
Optimization: Setting Rector memory limit to 384M for small project
Optimization: Standard processing mode with caching enabled
Performance: Analysis completed in 12.4 seconds
```

**Large Project Processing:**
```
Project Analysis: Found 2,847 files (423 PHP files) in /packages
Optimization: Setting Rector memory limit to 1200M for large project
Optimization: Enabling parallel processing, caching, and chunked processing
Performance: Applied 43 changes in 3m 12s (estimated 6m 45s without optimization)
Memory Usage: Peak 847M / 1200M allocated
```

## Troubleshooting

### Memory Issues

If you still encounter memory issues with automatic optimization:

```shell
# Increase memory manually
vendor/bin/qt fix:rector --memory-limit=2048M

# Check optimization decisions
vendor/bin/qt fix:rector --show-optimization

# Disable optimization if needed
vendor/bin/qt fix:rector --no-optimization
```

### Performance Issues

```shell
# Verify optimization is working
vendor/bin/qt lint:rector --show-optimization

# Should show project analysis and optimization decisions
# If not optimizing, check TYPO3 project detection
```

### Large Codebase Processing

```shell
# For very large codebases, use maximum memory
vendor/bin/qt fix:rector --memory-limit=2048M

# Or process specific directories
vendor/bin/qt fix:rector --path=./packages/specific-extension

# Clear cache if experiencing issues
rm -rf /tmp/rector_cache
```

### Common Error Resolution

**"Allowed memory size exhausted" (Before Optimization):**
```
# This error is now prevented by automatic memory allocation
# But if you see it with --no-optimization, use:
vendor/bin/qt fix:rector --memory-limit=2048M
```

**"No files found to process" (Path Issues):**
```
# Check if you're in the right directory
vendor/bin/qt lint:rector --show-optimization

# Or specify custom path
vendor/bin/qt lint:rector --path=./src
```

**Slow Performance on Large Projects:**
```
# Verify caching and parallel processing are enabled
vendor/bin/qt fix:rector --show-optimization

# Should show "Enabling parallel processing and caching"
```

## TYPO3-Specific Optimizations

### Automatic TYPO3 Detection

The system automatically detects TYPO3 projects and applies specific optimizations:

- **Path Scoping**: Defaults to `/packages` directory instead of entire project
- **TYPO3 Rules**: Uses TYPO3-specific Rector rules for optimal modernization  
- **Configuration**: Automatically uses TYPO3 13.4 target configuration
- **Performance**: Optimized for typical TYPO3 project structures

### Memory Multipliers

Rector uses the highest memory multiplier (3.0x) among all tools because:
- **AST Transformation**: Code transformation requires significant memory
- **Rule Processing**: Multiple rules processed simultaneously
- **File Caching**: In-memory caching of parsed files
- **Dependency Analysis**: Complex dependency graph analysis

This ensures reliable processing even on the most complex TYPO3 installations.
