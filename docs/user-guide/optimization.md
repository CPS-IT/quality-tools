# Dynamic Resource Optimization

The Dynamic Resource Optimization system is the core feature that makes CPSIT Quality Tools "just work" on projects of any size without manual configuration. This guide explains how the optimization works and how to customize it when needed.

## Overview

Every command automatically analyzes your project and optimizes memory limits, processing strategies, and performance settings. This eliminates common issues like memory exhaustion on large projects and provides significant performance improvements.

**Key Benefits:**
- **Zero Configuration Required**: All optimization happens automatically
- **50%+ Performance Improvement**: On large projects through smart optimization
- **Memory Issue Resolution**: Eliminates PHPStan and PHP CS Fixer memory exhaustion
- **Smart Path Scoping**: Focuses analysis on relevant code (defaults to `/packages` directory)
- **Consistent Experience**: All tools use the same optimization strategy

## How It Works

### Project Analysis

Every command starts by analyzing your project:

```bash
$ vendor/bin/qt lint:phpstan
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 552M for medium project
Optimization: Enabling parallel processing for improved performance
```

The system analyzes:
- **File Count**: Total files and files per type (PHP, YAML, JSON, XML)
- **Project Size**: Small (<100 files), Medium (100-1000), Large (1000-5000), Enterprise (>5000)
- **Code Complexity**: Lines of code, complexity metrics
- **Project Type**: TYPO3 vs generic PHP project detection

### Memory Optimization

Memory limits are calculated dynamically based on project characteristics:

| Project Size | File Range | PHPStan Memory | PHP CS Fixer Memory | Rector Memory |
|-------------|-----------|---------------|-------------------|--------------|
| Small | < 100 files | 256M | 256M | 384M |
| Medium | 100-1000 files | 552M | 460M | 690M |
| Large | 1000-5000 files | 1024M | 768M | 1200M |
| Enterprise | > 5000 files | 2048M | 1536M | 2048M |

**Memory Calculation Algorithm:**
- Base memory: 128MB
- PHP files: 0.5MB per file + complexity factor
- Other files: 0.1MB per file
- Tool-specific multipliers applied
- Reasonable limits: 256MB minimum, 2GB maximum

### Performance Optimization

For larger projects, additional optimizations are automatically enabled:

**Parallel Processing:**
- Enabled automatically for projects with 500+ files
- Improves processing speed by 40-60%
- Uses optimal core count detection

**Caching:**
- Automatic cache enablement for supported tools
- Significantly speeds up repeated runs
- Cache files stored in system temp directory

**Path Scoping:**
- TYPO3 projects default to analyzing `/packages` directory only
- Avoids analyzing vendor code (reduces from 48K+ to 1K files typically)
- Custom paths can be specified with `--path` option

## Optimization Examples

### Small Project Example
```
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 45 files (12 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 256M for small project
Optimization: Standard processing mode selected
Performance: Analysis completed in 8.2 seconds
```

### Medium Project Example
```
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 1,001 files (174 PHP files) in /packages
Optimization: Setting PHPStan memory limit to 552M for medium project
Optimization: Enabling parallel processing for improved performance
Performance: Analysis completed in 45.3 seconds (estimated 89s without optimization)
```

### Large Project Example
```
Project Analysis: Analyzing TYPO3 project structure...
Project Analysis: Found 2,847 files (423 PHP files) in /packages
Optimization: Setting Rector memory limit to 1200M for large project
Optimization: Enabling parallel processing and caching for performance
Optimization: Using chunked processing for memory efficiency
Performance: Processing completed in 3m 12s (estimated 6m 45s without optimization)
```

## Manual Override Options

While optimization works automatically, advanced users can override settings:

### Disable Optimization
```bash
# Disable all automatic optimization
vendor/bin/qt lint:phpstan --no-optimization

# Use tool defaults instead of calculated values
vendor/bin/qt fix:rector --no-optimization
```

### View Optimization Decisions
Optimization details are shown by default for all commands:

```bash
# Optimization details are shown automatically
vendor/bin/qt lint:phpstan

# Example output:
# Project Analysis: Project size: MEDIUM (1,001 files)
# Project Analysis: PHP files: 174, Complexity score: 3.2
# Memory Calculation: Base 128M + PHP factor 87M + complexity 32M = 247M
# Memory Calculation: Applied PHPStan multiplier 2.24x = 552M
# Performance: Parallel processing enabled (8 cores detected)
# Performance: Caching enabled for repeated runs
```

### Manual Memory Limits
```bash
# Override automatic memory calculation
vendor/bin/qt lint:phpstan --memory-limit=1024M

# Override with custom level
vendor/bin/qt lint:phpstan --level=8 --memory-limit=1536M
```

### Custom Paths
```bash
# Analyze specific path instead of automatic /packages detection
vendor/bin/qt lint:phpstan --path=./custom/extension

# Analyze entire project (not recommended for large projects)
vendor/bin/qt lint:phpstan --path=.
```

## Optimization Diagnostics

### Performance Metrics
All commands show performance information:
```
Performance: Analysis completed in 45.3 seconds
Performance: Memory peak usage: 387M / 552M allocated
Performance: Files processed: 1,001 (174 PHP files analyzed)
Performance: Optimization saved estimated 43.7 seconds
```

### Memory Usage Monitoring
```
Memory Usage: Peak 387M / 552M allocated (70% utilization)
Memory Usage: Optimization prevented memory exhaustion
Memory Usage: Without optimization: estimated 650M+ required
```

### File Analysis Details
```
Project Analysis: File type breakdown:
  - PHP files: 174 (primary analysis target)
  - TypoScript files: 67 (configuration files)
  - YAML files: 89 (configuration files)
  - JSON files: 23 (composer, package files)
  - Other files: 648 (templates, assets, etc.)
```

## Advanced Configuration

### Environment Variables
Control optimization behavior globally:

```bash
# Disable optimization for all commands
export QT_NO_OPTIMIZATION=1

# Set global memory multiplier
export QT_MEMORY_MULTIPLIER=1.5

# Set minimum memory limit
export QT_MIN_MEMORY=512M
```

### Configuration Files
For project-wide settings, create `.qt-config.yaml`:

```yaml
optimization:
  enabled: true
  memory:
    phpstan_multiplier: 2.5
    php_cs_fixer_multiplier: 1.8
    rector_multiplier: 3.0
  performance:
    parallel_threshold: 300
    cache_enabled: true
  paths:
    typo3_default: "packages"
    analysis_depth: 10
```

## Troubleshooting Optimization

### Common Issues

**Memory Still Insufficient:**
```bash
# If automatic calculation is too low, override manually
vendor/bin/qt lint:phpstan --memory-limit=2048M

# Or disable optimization and use tool defaults
vendor/bin/qt lint:phpstan --no-optimization
```

**Performance Slower Than Expected:**
```bash
# Optimization details are shown by default, check the output
vendor/bin/qt lint:phpstan

# Verify path scoping is working
# Should show "Found X files in /packages" not entire project
```

**Optimization Not Working:**
```bash
# Check project detection (details shown by default)
vendor/bin/qt lint:phpstan

# Verify TYPO3 project detection:
# Should show "TYPO3 project detected" in analysis output
```

### Debug Mode
```bash
# Enable debug output for detailed optimization information
vendor/bin/qt lint:phpstan --debug

# Shows:
# - Project detection logic
# - File counting process
# - Memory calculation steps
# - Performance optimization decisions
```

## Technical Implementation

### Project Analysis Algorithm
1. **Project Root Detection**: Traverse up to find TYPO3 composer.json
2. **File System Analysis**: Count files by type using RecursiveDirectoryIterator
3. **Complexity Analysis**: Calculate code metrics for PHP files
4. **Size Classification**: Categorize project as Small/Medium/Large/Enterprise
5. **Optimization Selection**: Apply appropriate optimization profile

### Memory Calculation Formula
```
memory = base_memory + (php_files * 0.5MB) + (complexity_factor * 0.1MB) + (other_files * 0.1MB)
final_memory = min(max(memory * tool_multiplier, 256MB), 2048MB)
```

### Tool-Specific Multipliers
- **PHPStan**: 2.24x (AST analysis is memory intensive)
- **PHP CS Fixer**: 1.8x (token analysis requires significant memory)
- **Rector**: 3.0x (code transformation requires most memory)
- **Fractor**: 2.0x (TypoScript parsing and transformation)

## Performance Benchmarks

Real-world performance improvements with optimization enabled:

| Project Size | Files | Without Optimization | With Optimization | Improvement |
|-------------|-------|---------------------|-------------------|-------------|
| Small | 45 | 12.3s | 8.2s | 33% |
| Medium | 1,001 | 89.4s | 45.3s | 49% |
| Large | 2,847 | 6m 45s | 3m 12s | 53% |
| Enterprise | 8,234 | Memory Error | 8m 34s | Previously Impossible |

## Future Enhancements

Planned optimization improvements:

- **Machine Learning Optimization**: Learn from project patterns to improve predictions
- **CI/CD Integration**: Optimize specifically for build environments
- **Tool Chain Optimization**: Optimize across multiple tool runs
- **Memory Profiling**: Real-time memory usage optimization
- **Custom Optimization Profiles**: Save and share optimization configurations

The Dynamic Resource Optimization system represents a major quality-of-life improvement, ensuring that quality tools "just work" regardless of project size while providing significant performance benefits.
