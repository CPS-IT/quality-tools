# Environment Variables Guide

This guide covers using environment variables in YAML configuration files to create flexible, environment-specific setups.

## Overview

Environment variables allow you to:
- **Customize configuration per environment** (development, testing, production)
- **Keep sensitive information out of version control**
- **Share configurations across team members with different setups**
- **Simplify CI/CD pipeline configuration**
- **Make configurations more flexible and reusable**

## Syntax

The YAML configuration system supports standard shell-style environment variable syntax:

| Syntax | Description | Behavior if Variable Missing |
|--------|-------------|------------------------------|
| `${VAR}` | Required variable | Throws error |
| `${VAR:-default}` | Optional with default | Uses default value |
| `${VAR:default}` | Alternative syntax | Same as above |

## Basic Usage Examples

### Simple Variable Substitution

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME}"
    php_version: "${PHP_VERSION}"
    typo3_version: "${TYPO3_VERSION}"
```

**Environment:**
```bash
export PROJECT_NAME="my-awesome-project"
export PHP_VERSION="8.3"
export TYPO3_VERSION="13.4"
```

### Variables with Defaults

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-default-project}"
    php_version: "${PHP_VERSION:-8.3}"
    typo3_version: "${TYPO3_VERSION:-13.4}"

  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"
      memory_limit: "${PHPSTAN_MEMORY:-1G}"
```

This configuration works even without any environment variables set, using the default values.

## Type-Safe Variable Conversion

Environment variables are automatically converted to the correct data types:

### String Values
```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-my-project}"  # Remains string
```

### Integer Values
```yaml
quality-tools:
  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"  # Converted to integer
    
  performance:
    max_processes: "${MAX_PROCESSES:-4}"  # Converted to integer
```

### Boolean Values
```yaml
quality-tools:
  tools:
    rector:
      enabled: "${ENABLE_RECTOR:-true}"  # Converted to boolean
    
  output:
    colors: "${ENABLE_COLORS:-false}"  # Converted to boolean

  performance:
    parallel: "${ENABLE_PARALLEL:-1}"  # "1" becomes true, "0" becomes false
```

**Supported boolean values:**
- **True**: `"true"`, `"1"`, `"yes"`, `"on"`
- **False**: `"false"`, `"0"`, `"no"`, `"off"`

### Array Values (Advanced)
```yaml
quality-tools:
  paths:
    scan:
      - "${SCAN_PATH_1:-packages/}"
      - "${SCAN_PATH_2:-config/system/}"
    exclude:
      - "${EXCLUDE_PATH_1:-var/}"
      - "${EXCLUDE_PATH_2:-vendor/}"
```

## Environment-Specific Configurations

### Development Environment

**Configuration:**
```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-dev-project}"

  tools:
    rector:
      enabled: "${ENABLE_RECTOR:-true}"
      dry_run: "${RECTOR_DRY_RUN:-true}"  # Always dry-run in dev

    phpstan:
      level: "${PHPSTAN_LEVEL:-5}"  # Lower level for faster development
      memory_limit: "${PHPSTAN_MEMORY:-512M}"

  output:
    verbosity: "${OUTPUT_VERBOSITY:-verbose}"
    colors: "${ENABLE_COLORS:-true}"

  performance:
    parallel: "${ENABLE_PARALLEL:-false}"  # Easier debugging
```

**Environment file (.env.development):**
```bash
PROJECT_NAME="my-project-dev"
ENABLE_RECTOR="true"
RECTOR_DRY_RUN="true"
PHPSTAN_LEVEL="5"
PHPSTAN_MEMORY="512M"
OUTPUT_VERBOSITY="verbose"
ENABLE_COLORS="true"
ENABLE_PARALLEL="false"
```

### Testing Environment

**Environment file (.env.testing):**
```bash
PROJECT_NAME="my-project-test"
ENABLE_RECTOR="true"
RECTOR_DRY_RUN="false"
PHPSTAN_LEVEL="7"
PHPSTAN_MEMORY="1G"
OUTPUT_VERBOSITY="normal"
ENABLE_COLORS="false"
ENABLE_PARALLEL="true"
```

### Production Analysis

**Environment file (.env.production):**
```bash
PROJECT_NAME="my-project-prod"
ENABLE_RECTOR="true"
RECTOR_DRY_RUN="true"
PHPSTAN_LEVEL="8"
PHPSTAN_MEMORY="2G"
OUTPUT_VERBOSITY="normal"
ENABLE_COLORS="false"
ENABLE_PARALLEL="true"
MAX_PROCESSES="8"
```

### CI/CD Environment

**Configuration:**
```yaml
quality-tools:
  project:
    name: "${CI_PROJECT_NAME:-ci-project}"

  tools:
    rector:
      enabled: "${CI_ENABLE_RECTOR:-true}"
      dry_run: true  # Always dry-run in CI

    phpstan:
      level: "${CI_PHPSTAN_LEVEL:-8}"  # Strict in CI
      memory_limit: "${CI_MEMORY_LIMIT:-2G}"

  output:
    colors: false  # No colors in CI logs
    progress: false  # No progress bars in CI
    verbosity: "${CI_VERBOSITY:-normal}"

  performance:
    parallel: "${CI_ENABLE_PARALLEL:-true}"
    max_processes: "${CI_PROCESSORS:-4}"
    cache_enabled: "${CI_ENABLE_CACHE:-false}"
```

**CI Environment Variables:**
```bash
# GitLab CI
CI_PROJECT_NAME="$CI_PROJECT_NAME"
CI_PHPSTAN_LEVEL="8"
CI_MEMORY_LIMIT="4G"
CI_PROCESSORS="$CI_PROCESSOR_COUNT"
CI_ENABLE_PARALLEL="true"
CI_ENABLE_CACHE="false"

# GitHub Actions
CI_PROJECT_NAME="$GITHUB_REPOSITORY"
CI_PROCESSORS="2"  # GitHub Actions default
```

## Advanced Patterns

### Conditional Tool Enablement

```yaml
quality-tools:
  tools:
    rector:
      enabled: "${ENABLE_RECTOR:-true}"
    
    phpstan:
      enabled: "${ENABLE_PHPSTAN:-true}"
      level: "${PHPSTAN_LEVEL:-6}"
    
    php-cs-fixer:
      enabled: "${ENABLE_PHP_CS_FIXER:-true}"
    
    fractor:
      enabled: "${ENABLE_FRACTOR:-false}"  # Disabled by default
```

**Environment file (.env.minimal):**
```bash
# Minimal analysis for quick checks
ENABLE_RECTOR="false"
ENABLE_PHPSTAN="true"
ENABLE_PHP_CS_FIXER="false"
ENABLE_FRACTOR="false"
```

### Memory Scaling by Project Size

```yaml
quality-tools:
  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"
      memory_limit: "${PHPSTAN_MEMORY:-1G}"
    
    rector:
      enabled: "${ENABLE_RECTOR:-true}"
      # Memory will be calculated automatically, but can override
  
  performance:
    parallel: "${ENABLE_PARALLEL:-true}"
    max_processes: "${MAX_PROCESSES:-4}"
```

**Environment files for different project sizes:**

**.env.small:**
```bash
PHPSTAN_MEMORY="512M"
MAX_PROCESSES="2"
ENABLE_PARALLEL="false"
```

**.env.medium:**
```bash
PHPSTAN_MEMORY="1G"
MAX_PROCESSES="4"
ENABLE_PARALLEL="true"
```

**.env.large:**
```bash
PHPSTAN_MEMORY="2G"
MAX_PROCESSES="8"
ENABLE_PARALLEL="true"
```

### Path Customization

```yaml
quality-tools:
  paths:
    scan:
      - "${SCAN_PATH_1:-packages/}"
      - "${SCAN_PATH_2:-config/system/}"
      - "${ADDITIONAL_SCAN_PATH}"  # Optional additional path
    exclude:
      - "${EXCLUDE_PATH_1:-var/}"
      - "${EXCLUDE_PATH_2:-vendor/}"
      - "${EXCLUDE_PATH_3:-node_modules/}"

  tools:
    phpstan:
      paths:  # PHPStan-specific paths
        - "${PHPSTAN_PATH_1:-packages/}"
        - "${PHPSTAN_PATH_2:-Tests/}"
```

**Environment:**
```bash
# Standard paths
SCAN_PATH_1="packages/"
SCAN_PATH_2="config/system/"

# Additional path for some environments
ADDITIONAL_SCAN_PATH="legacy/"

# PHPStan-specific paths
PHPSTAN_PATH_1="packages/"
PHPSTAN_PATH_2="Tests/"
```

## Working with .env Files

### Loading Environment Files

```bash
# Method 1: Export before running commands
export $(cat .env.development | xargs)
vendor/bin/qt config:show

# Method 2: Use with env command
env $(cat .env.testing | xargs) vendor/bin/qt lint:phpstan

# Method 3: Source the file
source .env.production
vendor/bin/qt fix:rector
```

### Example .env Files

**.env.base (common settings):**
```bash
# Project configuration
PROJECT_NAME="my-typo3-project"
PHP_VERSION="8.3"
TYPO3_VERSION="13.4"

# Common tool settings
ENABLE_RECTOR="true"
ENABLE_PHPSTAN="true"
ENABLE_PHP_CS_FIXER="true"

# Output settings
ENABLE_COLORS="true"
```

**.env.development:**
```bash
# Include base settings, then override
# Source: source .env.base && source .env.development

# Development-specific settings
PHPSTAN_LEVEL="5"
PHPSTAN_MEMORY="512M"
OUTPUT_VERBOSITY="verbose"
ENABLE_PARALLEL="false"
RECTOR_DRY_RUN="true"
```

**.env.ci:**
```bash
# CI-specific settings
PHPSTAN_LEVEL="8"
PHPSTAN_MEMORY="2G"
OUTPUT_VERBOSITY="normal"
ENABLE_COLORS="false"
ENABLE_PARALLEL="true"
MAX_PROCESSES="4"
CI_ENABLE_CACHE="false"
```

## Docker Integration

### Docker Compose

```yaml
# docker-compose.yml
version: '3.8'
services:
  quality-tools:
    image: php:8.3-cli
    environment:
      - PROJECT_NAME=docker-project
      - PHPSTAN_LEVEL=6
      - PHPSTAN_MEMORY=1G
      - ENABLE_PARALLEL=true
    volumes:
      - .:/app
    working_dir: /app
    command: vendor/bin/qt lint:phpstan
```

### Dockerfile

```dockerfile
# Dockerfile
FROM php:8.3-cli

# Set default environment variables
ENV PROJECT_NAME="docker-project"
ENV PHPSTAN_LEVEL="6"
ENV PHPSTAN_MEMORY="1G"
ENV ENABLE_PARALLEL="true"
ENV ENABLE_COLORS="false"

# Allow override at runtime
ARG BUILD_ENV=production
ENV BUILD_ENVIRONMENT=$BUILD_ENV

COPY . /app
WORKDIR /app

# Install quality tools
RUN composer install --dev

# Run quality checks
CMD ["vendor/bin/qt", "lint:phpstan"]
```

## Security Considerations

### Sensitive Information

**Never put sensitive information directly in YAML files:**

```yaml
# DON'T DO THIS
quality-tools:
  project:
    name: "secret-project-x"
    api_key: "abc123-secret-key"  # Don't put secrets in config
```

**Use environment variables for sensitive data:**

```yaml
# DO THIS INSTEAD
quality-tools:
  project:
    name: "${PROJECT_NAME}"
    # External services config (if needed)
    api_key: "${API_KEY}"  # Will be loaded from environment
```

### Environment File Security

```bash
# Add to .gitignore
echo ".env*" >> .gitignore
echo "!.env.example" >> .gitignore

# Create example file for team
cp .env.development .env.example
# Remove sensitive values from .env.example
```

### CI/CD Secrets

```yaml
# GitHub Actions
env:
  PROJECT_NAME: ${{ github.event.repository.name }}
  PHPSTAN_LEVEL: "8"
  API_KEY: ${{ secrets.API_KEY }}  # Use GitHub secrets

# GitLab CI
variables:
  PROJECT_NAME: $CI_PROJECT_NAME
  PHPSTAN_LEVEL: "8"
  # API_KEY set in GitLab CI/CD variables
```

## Debugging Environment Variables

### Check Variable Resolution

```bash
# Show resolved configuration
vendor/bin/qt config:show

# Show with sources (verbose)
vendor/bin/qt config:show --verbose

# Show as JSON for easier parsing
vendor/bin/qt config:show --format=json
```

### Debug Specific Variables

```bash
# Check if variables are set
echo "PROJECT_NAME: $PROJECT_NAME"
echo "PHPSTAN_LEVEL: $PHPSTAN_LEVEL"

# Check what the configuration sees
vendor/bin/qt config:show | grep -A 10 "project:"

# Test variable interpolation
echo "Resolved project name: ${PROJECT_NAME:-default-name}"
```

### Common Issues and Solutions

1. **Variable not found error**
   ```bash
   # Error: Environment variable "PROJECT_NAME" is not set
   
   # Solution: Set the variable or add a default
   export PROJECT_NAME="my-project"
   # Or in YAML: "${PROJECT_NAME:-default-project}"
   ```

2. **Boolean conversion issues**
   ```bash
   # Variable set but not converting to boolean
   export ENABLE_PARALLEL="True"  # Capital T won't work
   export ENABLE_PARALLEL="true"  # Lowercase works
   ```

3. **Type conversion problems**
   ```bash
   # Integer expected but string provided
   export PHPSTAN_LEVEL="six"     # Won't work
   export PHPSTAN_LEVEL="6"       # Works
   ```

## Best Practices

### Organization

1. **Use consistent naming conventions**
   ```bash
   # Good: Consistent prefixes
   PROJECT_NAME="my-project"
   PROJECT_VERSION="1.0.0"
   
   # Good: Tool-specific prefixes
   PHPSTAN_LEVEL="6"
   PHPSTAN_MEMORY="1G"
   
   RECTOR_ENABLED="true"
   RECTOR_DRY_RUN="false"
   ```

2. **Group related variables**
   ```bash
   # Project settings
   PROJECT_NAME="my-project"
   PROJECT_ENV="development"
   
   # PHPStan settings
   PHPSTAN_LEVEL="6"
   PHPSTAN_MEMORY="1G"
   PHPSTAN_ENABLED="true"
   
   # Output settings
   OUTPUT_COLORS="true"
   OUTPUT_VERBOSITY="normal"
   ```

### Documentation

Document your environment variables:

```yaml
# .quality-tools.yaml
# Environment variables used:
# - PROJECT_NAME: Project name (default: "default-project")
# - PHP_VERSION: PHP version (default: "8.3")
# - PHPSTAN_LEVEL: PHPStan analysis level (default: 6)
# - PHPSTAN_MEMORY: PHPStan memory limit (default: "1G")
# - ENABLE_PARALLEL: Enable parallel processing (default: true)

quality-tools:
  project:
    name: "${PROJECT_NAME:-default-project}"
    php_version: "${PHP_VERSION:-8.3}"
  # ... rest of configuration
```

Create an example file:

```bash
# .env.example
# Copy to .env and customize for your environment

# Project Configuration
PROJECT_NAME="my-project"
PHP_VERSION="8.3"
TYPO3_VERSION="13.4"

# Tool Configuration
PHPSTAN_LEVEL="6"
PHPSTAN_MEMORY="1G"
ENABLE_RECTOR="true"
RECTOR_DRY_RUN="false"

# Performance
ENABLE_PARALLEL="true"
MAX_PROCESSES="4"

# Output
OUTPUT_VERBOSITY="normal"
ENABLE_COLORS="true"
```

This guide provides comprehensive coverage of environment variable usage in the YAML configuration system, from basic syntax to advanced patterns and best practices.