TypoScript Lint
===============

TypoScript Lint analyzes TypoScript files for syntax errors, coding standard violations, and various quality issues. This package provides both a convenient wrapper command and direct tool access for comprehensive TypoScript code quality checking.

## Quick Start

Use the wrapper command for most common scenarios:

```shell
# Analyze all TypoScript files in your project
$ bin/qt lint:typoscript

# Analyze specific directory
$ bin/qt lint:typoscript --path packages/my-extension/Configuration/TypoScript/
```

## Wrapper Command Usage

The `qt lint:typoscript` command provides a convenient wrapper with hybrid path handling:

### Default Behavior (Recommended)

Automatically discovers and analyzes all TypoScript files using configuration-based path patterns:

```shell
$ bin/qt lint:typoscript
```

This command:
- Uses optimized configuration file for TYPO3 projects
- Analyzes files matching `packages/**/Configuration/TypoScript` patterns
- Scans both `*.typoscript` and `*.tsconfig` files
- Provides informational feedback about path discovery method

### Custom Path Analysis

Target specific directories using the `--path` option:

```shell
# Analyze specific extension
$ bin/qt lint:typoscript --path packages/my-extension/Configuration/TypoScript/

# Analyze multiple files in a directory
$ bin/qt lint:typoscript --path config/sites/default/
```

When using `--path`:
- Validates that the target path exists
- Converts to positional arguments for the underlying tool
- Provides feedback about custom path analysis
- Only analyzes files in the specified directory

### Configuration Override

Use custom configuration file:

```shell
$ bin/qt lint:typoscript --config path/to/custom-typoscript-lint.yml
```

## Direct Tool Usage

For advanced use cases or CI/CD integration, use typoscript-lint directly:

### Default Configuration

Analyze all TypoScript files using the provided configuration:

```shell
$ vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml
```

### Custom Path as Positional Argument

Target specific directories by passing them as positional arguments:

```shell
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  packages/my-extension/Configuration/TypoScript/
```

### Multiple Paths

Analyze multiple directories in one command:

```shell
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  packages/extension-1/Configuration/TypoScript/ \
  packages/extension-2/Configuration/TypoScript/
```

### Output Formats

Control output format and destination:

```shell
# Checkstyle XML format
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  --format checkstyle \
  --output report.xml

# JSON format
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  --format json
```

## Configuration

The provided `typoscript-lint.yml` configuration is optimized for TYPO3 projects:

### Path Discovery Patterns

```yaml
paths:
  - packages/**/Configuration/TypoScript
  - packages/**/Configuration/TSconfig
filePatterns:
  - "*.typoscript"
  - "*.tsconfig"
```

These patterns automatically discover:
- All TypoScript files in package extensions
- TSconfig files for backend configuration
- Standard TYPO3 file extensions

### Quality Rules

The configuration includes comprehensive quality checks:

| Rule | Purpose |
|------|---------|
| **Indentation** | Enforces 2-space indentation with consistent formatting |
| **DeadCode** | Identifies unreachable or unused TypoScript code |
| **OperatorWhitespace** | Ensures proper spacing around operators |
| **RepeatingRValue** | Detects duplicate assignments with allowed exceptions |
| **DuplicateAssignment** | Prevents conflicting property assignments |
| **EmptySection** | Identifies empty TypoScript sections |
| **NestingConsistency** | Maintains consistent object nesting patterns |

### Allowed Exceptions

Some rules include exceptions for common TYPO3 patterns:

```yaml
sniffs:
  - class: RepeatingRValue
    parameters:
      allowedRightValues:
        - "TYPO3\\CMS\\Frontend\\DataProcessing\\DatabaseQueryProcessor"
```

## Common Use Cases

### Extension Development

Analyze a single extension during development:

```shell
# Using wrapper command
$ bin/qt lint:typoscript --path packages/my-extension/Configuration/TypoScript/

# Using direct tool
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  packages/my-extension/Configuration/TypoScript/
```

### Project-wide Analysis

Check all TypoScript files in your project:

```shell
# Using wrapper command (recommended)
$ bin/qt lint:typoscript

# Using direct tool
$ vendor/bin/typoscript-lint -c vendor/cpsit/quality-tools/config/typoscript-lint.yml
```

### CI/CD Integration

For automated testing environments:

```shell
# Exit with error code on violations
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  --exit-code \
  --fail-on-warnings
```

### Custom Configuration

Create project-specific configuration by extending the base configuration:

```yaml
# project-typoscript-lint.yml
paths:
  - packages/**/Configuration/TypoScript
  - config/sites/**/
filePatterns:
  - "*.typoscript"
  - "*.tsconfig"
  - "*.txt"  # Legacy TypoScript files
sniffs:
  - class: Indentation
    parameters:
      useSpaces: true
      indentPerLevel: 4  # Custom indentation
      indentConditions: true
```

## Recent Improvements

The wrapper command was enhanced to resolve path handling issues:

### Hybrid Path Handling

- **Default Mode**: Uses configuration-based path discovery for zero-configuration operation
- **Custom Path Mode**: Accepts `--path` option and converts to positional arguments
- **User Feedback**: Provides clear information about which path discovery method is being used

### Issue Resolution

Previously, the `--path` option caused errors because the underlying tool doesn't support this option format. The hybrid approach now:

1. Uses optimal configuration file patterns by default
2. Converts `--path` options to positional arguments when specified
3. Validates custom paths before execution
4. Provides informative feedback to users

This ensures consistent behavior across all quality tools while leveraging each tool's native capabilities.

## Troubleshooting

### Path Not Found

If you encounter path-related errors:

```shell
# Verify the path exists
$ ls -la packages/my-extension/Configuration/TypoScript/

# Use absolute path if relative path fails
$ bin/qt lint:typoscript --path $(pwd)/packages/my-extension/Configuration/TypoScript/
```

### No Files Found

If no TypoScript files are found:

- Verify file extensions match the configuration (`.typoscript`, `.tsconfig`)
- Check that files are in expected directory structures
- Use direct tool with specific path to test discovery

### Configuration Issues

To debug configuration problems:

```shell
# Test with verbose output
$ vendor/bin/typoscript-lint \
  -c vendor/cpsit/quality-tools/config/typoscript-lint.yml \
  -v
```
