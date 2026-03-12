TYPO3 Fractor
=============

TYPO3 Fractor TypoScript modernization tool with automatic resource optimization.

## CLI Command Usage (Recommended)

### Lint Command (Analysis Only)

Use the CLI command to analyze TypoScript modernization opportunities with automatic optimization:

```shell
# Lint command with automatic optimization
$ vendor/bin/qt lint:fractor
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (67 TypoScript files) in /packages
Optimization: Setting Fractor memory limit to 460M for medium project
Optimization: Enabling optimized TypoScript processing

[Fractor analysis follows...]
```

### Fix Command (Apply Changes)

Apply TypoScript modernization with automatic optimization:

```shell
# Fix command with automatic optimization
$ vendor/bin/qt fix:fractor
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (67 TypoScript files) in /packages
Optimization: Setting Fractor memory limit to 460M for medium project
Optimization: Processing TypoScript files with optimized configuration

[Fractor fixes applied...]
```

**Key Benefits:**
- **Automatic Memory Management**: Dynamically calculates optimal memory limit (460M for medium projects)
- **Smart Path Scoping**: Analyzes `/packages` and `config/sites` directories for TYPO3 projects
- **TypoScript Optimization**: Specifically optimized for TypoScript parsing and transformation
- **Zero Configuration**: Works optimally without any setup

### CLI Options

```shell
# Basic analysis
vendor/bin/qt lint:fractor

# Apply fixes
vendor/bin/qt fix:fractor

# Custom path
vendor/bin/qt lint:fractor --path=./custom/extension

# Custom memory limit (overrides automatic calculation)
vendor/bin/qt fix:fractor --memory-limit=768M

# Disable automatic optimization
vendor/bin/qt lint:fractor --no-optimization

# Optimization details shown by default
# (use --no-optimization to disable)
```

## Direct Tool Usage (Alternative)

### Default Configuration (Analysis Only)

For direct tool usage without optimization:

```shell
$ app/vendor/bin/fractor process --dry-run -c app/vendor/cpsit/quality-tools/config/fractor.php
```

**Note**: Direct usage does not include automatic optimization and may encounter memory issues on large projects.

### Apply Fixes (Direct Usage)

Apply fixes directly without optimization:

```shell
$ app/vendor/bin/fractor process -c app/vendor/cpsit/quality-tools/config/fractor.php
```

## Optimization Details

### Automatic Memory Allocation

Fractor memory limits are automatically calculated based on project size:

| Project Size | File Range | Memory Limit | Typical Use Case |
|-------------|-----------|--------------|------------------|
| Small | < 100 files | 256M | Small extensions |
| Medium | 100-1000 files | 460M | Standard TYPO3 sites |
| Large | 1000-5000 files | 768M | Complex multi-site projects |
| Enterprise | > 5000 files | 1536M | Large enterprise installations |

### Performance Optimization

**Automatic Features:**
- **TypoScript Focus**: Optimized specifically for TypoScript file processing
- **Smart Scoping**: Analyzes both `/packages` and `/config/sites` directories
- **Memory Efficiency**: Prevents memory exhaustion during TypoScript parsing
- **Progress Indication**: Shows processing progress for operations with many TypoScript files

### Example Output

**Small Project Analysis:**
```
Project Analysis: Found 45 files (8 TypoScript files) in /packages
Optimization: Setting Fractor memory limit to 256M for small project
Optimization: Standard TypoScript processing mode
Performance: Analysis completed in 4.7 seconds
```

**Large Project Processing:**
```
Project Analysis: Found 2,847 files (167 TypoScript files) in /packages, /config/sites
Optimization: Setting Fractor memory limit to 768M for large project
Optimization: Enabling optimized TypoScript processing for performance
Performance: Processed 89 TypoScript changes in 1m 34s
Memory Usage: Peak 432M / 768M allocated
```

## Troubleshooting

### Memory Issues

If you still encounter memory issues with automatic optimization:

```shell
# Increase memory manually
vendor/bin/qt fix:fractor --memory-limit=1024M

# Check optimization decisions
vendor/bin/qt fix:fractor

# Disable optimization if needed
vendor/bin/qt fix:fractor --no-optimization
```

### Performance Issues

```shell
# Verify optimization is working
vendor/bin/qt lint:fractor

# Should show project analysis and optimization decisions
# If not optimizing, check TYPO3 project detection
```

### TypoScript File Detection

```shell
# Check if TypoScript files are being found
vendor/bin/qt lint:fractor

# Should show "Found X TypoScript files" in analysis
# If not finding files, check file extensions and locations
```

### Common Error Resolution

**"No TypoScript files found" (Path Issues):**
```
# Check if you're in the right directory
vendor/bin/qt lint:fractor

# Or specify custom path
vendor/bin/qt lint:fractor --path=./Configuration/TypoScript
```

**"YAML Parser Crash" (Known Issue):**
```
# This is a known issue being addressed
# Use direct tool usage as workaround:
app/vendor/bin/fractor process --dry-run -c app/vendor/cpsit/quality-tools/config/fractor.php
```

**Performance Issues on Large Sites:**
```
# Process specific site configuration
vendor/bin/qt fix:fractor --path=./config/sites/mysite

# Or increase memory for complex TypoScript
vendor/bin/qt fix:fractor --memory-limit=1024M
```

## TYPO3-Specific Optimizations

### Automatic Path Detection

The system automatically detects TYPO3 projects and analyzes relevant directories:

- **Package Extensions**: Scans `/packages` directory for extension TypoScript
- **Site Configuration**: Includes `/config/sites` for multi-site setups
- **TypoScript Files**: Focuses on `.typoscript`, `.ts`, and `.txt` files
- **Configuration**: Automatically uses TYPO3-specific Fractor rules

### TypoScript Processing

Fractor is specifically optimized for TypoScript modernization:

- **Syntax Updates**: Modernizes old TypoScript syntax to current standards
- **Structure Improvements**: Optimizes TypoScript structure and organization
- **Performance Enhancements**: Improves TypoScript performance characteristics
- **Best Practices**: Applies TYPO3 TypoScript best practices automatically

### Memory Considerations

Fractor uses a moderate memory multiplier (2.0x) because:
- **TypoScript Parsing**: TypoScript files are generally smaller than PHP files
- **Transformation Rules**: Less complex than PHP AST transformations
- **File Count**: Typically fewer TypoScript files than PHP files in projects
- **Processing**: Lighter processing requirements than PHP code analysis

This ensures efficient processing while maintaining reliability for complex TypoScript structures.
