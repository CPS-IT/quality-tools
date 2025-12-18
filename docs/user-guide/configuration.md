# Configuration

CPSIT Quality Tools can be customized using environment variables and runtime options. This guide covers all available configuration options.

## Environment Variables

### QT_PROJECT_ROOT

Override automatic TYPO3 project detection by specifying the project root directory.

**Usage:**
```bash
export QT_PROJECT_ROOT=/path/to/your/typo3/project
```

**Examples:**

```bash
# Unix/Linux/macOS - Temporary override
QT_PROJECT_ROOT=/var/www/mysite vendor/bin/qt --version

# Unix/Linux/macOS - Persistent override
export QT_PROJECT_ROOT=/var/www/mysite
vendor/bin/qt --version

# Windows - Temporary override
set QT_PROJECT_ROOT=C:\xampp\htdocs\mysite && vendor\bin\qt --version

# Windows - Persistent override
setx QT_PROJECT_ROOT "C:\xampp\htdocs\mysite"
vendor\bin\qt --version
```

**When to Use:**
- Automatic project detection fails
- Working with non-standard project structures
- Running from outside the project directory
- CI/CD pipeline integration
- Docker container environments

**Requirements:**
- Path must exist and be readable
- Path must be a directory
- Directory should contain a valid TYPO3 project

### QT_DEBUG

Enable debug mode to get detailed error information and execution traces.

**Usage:**
```bash
export QT_DEBUG=true
```

**Examples:**

```bash
# Temporary debug mode
QT_DEBUG=true vendor/bin/qt --version

# Persistent debug mode
export QT_DEBUG=true
vendor/bin/qt --version
```

**Debug Output Includes:**
- Detailed error messages
- Full exception stack traces
- Project detection steps
- File system traversal information
- Dependency validation results

**When to Use:**
- Troubleshooting project detection issues
- Investigating command failures
- Development and testing
- Bug reporting

## Command-Line Options

### Global Options

These options are available for all commands:

| Option      | Short  | Description                                     | Default  |
|-------------|--------|-------------------------------------------------|----------|
| `--help`    | `-h`   | Display help information                        | -        |
| `--version` | `-V`   | Display version information                     | -        |
| `--verbose` | `-v`   | Increase verbosity (can be used multiple times) | Normal   |
| `--quiet`   | `-q`   | Suppress output                                 | -        |

### Verbosity Levels

Control output detail with the verbose option:

```bash
# Normal output
vendor/bin/qt --version

# Verbose output (-v)
vendor/bin/qt --version -v

# Very verbose output (-vv)
vendor/bin/qt --version -vv

# Debug level output (-vvv)
vendor/bin/qt --version -vvv
```

**Verbosity Levels:**
- **Normal**: Standard output with essential information
- **Verbose (-v)**: Additional operational details
- **Very Verbose (-vv)**: Detailed process information
- **Debug (-vvv)**: Full debug information including internal state

### Quiet Mode

Suppress all output except errors:

```bash
# Quiet execution
vendor/bin/qt --version --quiet

# Combine with other options
vendor/bin/qt --quiet [command]
```

## Configuration Files

### Current Status

The current implementation uses environment variables for configuration. Configuration files are planned for future releases.

### Planned Configuration

Future versions will support configuration files:

```yaml
# .qt-config.yml (planned)
project:
  root: /path/to/project
  auto_detect: true

output:
  verbosity: normal
  debug: false

tools:
  rector:
    enabled: true
    config: config/rector.php
  phpstan:
    enabled: true
    level: 6
```

## Runtime Configuration

### Working Directory Behavior

The tool adapts its behavior based on where it's executed:

```bash
# From project root - Uses current directory
/path/to/project$ vendor/bin/qt --version

# From subdirectory - Searches upward automatically
/path/to/project/packages/extension$ ../../vendor/bin/qt --version

# With explicit project root - Uses specified directory
/anywhere$ QT_PROJECT_ROOT=/path/to/project vendor/bin/qt --version
```

### Path Resolution

The tool resolves paths in this order:

1. **QT_PROJECT_ROOT environment variable** (if set)
2. **Automatic detection** from current working directory
3. **Error** if no valid TYPO3 project found

## Integration Examples

### Development Environment

Set up your development environment with persistent configuration:

```bash
# ~/.bashrc or ~/.zshrc
export QT_PROJECT_ROOT=/var/www/myproject
export QT_DEBUG=false

# Project-specific .env file
echo "QT_PROJECT_ROOT=$(pwd)" >> .env
echo "QT_DEBUG=false" >> .env
source .env
```

### CI/CD Integration

Configure for continuous integration:

```yaml
# GitHub Actions example
env:
  QT_PROJECT_ROOT: ${{ github.workspace }}
  QT_DEBUG: false

steps:
  - name: Run Quality Tools
    run: vendor/bin/qt --version --quiet
```

```yaml
# GitLab CI example
variables:
  QT_PROJECT_ROOT: $CI_PROJECT_DIR
  QT_DEBUG: "false"

script:
  - vendor/bin/qt --version --quiet
```

### Docker Integration

Use environment variables in Docker containers:

```dockerfile
# Dockerfile
ENV QT_PROJECT_ROOT=/var/www/html
ENV QT_DEBUG=false

# Or with docker run
docker run -e QT_PROJECT_ROOT=/app -e QT_DEBUG=true myimage vendor/bin/qt --version
```

```yaml
# docker-compose.yml
services:
  web:
    environment:
      - QT_PROJECT_ROOT=/var/www/html
      - QT_DEBUG=false
```

## Configuration Validation

### Environment Variable Validation

The tool validates environment variables at runtime:

```bash
# Valid path
export QT_PROJECT_ROOT=/valid/typo3/project
vendor/bin/qt --version  # Works

# Invalid path
export QT_PROJECT_ROOT=/nonexistent/path
vendor/bin/qt --version  # ✗ Error: Directory doesn't exist

# Not a directory
export QT_PROJECT_ROOT=/path/to/file.txt
vendor/bin/qt --version  # ✗ Error: Not a directory

# No TYPO3 project
export QT_PROJECT_ROOT=/valid/but/not/typo3
vendor/bin/qt --version  # ✗ Error: No TYPO3 project found
```

### Debug Configuration Validation

Test your configuration with debug mode:

```bash
QT_DEBUG=true vendor/bin/qt --version
```

Expected debug output:
```
Project root detection started
Environment variable QT_PROJECT_ROOT: /path/to/project
Validating project root: /path/to/project
Found composer.json: /path/to/project/composer.json
TYPO3 dependencies found: typo3/cms-core
Project root confirmed: /path/to/project
CPSIT Quality Tools 1.0.0-dev
```

## Best Practices

### Development Setup

1. **Use project-relative paths**: Avoid absolute paths when possible
2. **Environment isolation**: Use different configurations for development/staging/production
3. **Version control**: Don't commit environment-specific configuration
4. **Documentation**: Document any required environment variables

### Production Considerations

1. **Security**: Be cautious with paths in shared environments
2. **Performance**: Disable debug mode in production
3. **Monitoring**: Use quiet mode for automated scripts
4. **Validation**: Always validate paths exist and are accessible

### Team Collaboration

1. **Shared standards**: Document required environment setup
2. **Default behavior**: Rely on automatic detection when possible
3. **Flexibility**: Provide override options for different development setups
4. **Testing**: Test configuration on different environments
