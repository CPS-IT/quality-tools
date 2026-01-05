# Project Templates Guide

This guide covers the project template system that provides pre-configured setups for different types of TYPO3 projects.

## Overview

Project templates provide ready-to-use configurations optimized for specific project types. Instead of starting from scratch, you can initialize your configuration with a template that matches your project structure and requirements.

**Benefits:**
- **Quick Setup**: Get started in seconds with optimized defaults
- **Best Practices**: Templates follow TYPO3 and project-specific best practices
- **Customizable**: Templates serve as starting points for further customization
- **Consistent**: Teams can use the same base configuration across projects

## Available Templates

| Template | Best For | Key Features |
|----------|----------|--------------|
| `default` | General TYPO3 projects | Balanced configuration for typical projects |
| `typo3-extension` | Extension development | High analysis level, extension-specific paths |
| `typo3-site-package` | Site package projects | Site-focused paths, moderate analysis |
| `typo3-distribution` | TYPO3 distributions | Distribution paths, high performance settings |

## Template Details

### Default Template

**Use Case:** General TYPO3 projects, starting point for custom configurations

**Command:**
```bash
vendor/bin/qt config:init --template=default
```

**Configuration:**
```yaml
quality-tools:
  project:
    name: "project-name"  # Detected from composer.json or directory
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/system/"
    exclude:
      - "var/"
      - "vendor/"
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

  output:
    verbosity: "normal"
    colors: true
    progress: true

  performance:
    parallel: true
    max_processes: 4
    cache_enabled: true
```

### TYPO3 Extension Template

**Use Case:** Developing TYPO3 extensions for TER (TYPO3 Extension Repository)

**Command:**
```bash
vendor/bin/qt config:init --template=typo3-extension
```

**Key Differences:**
- **Higher PHPStan level (8)** - Stricter analysis for public extensions
- **Extension-specific paths** - Classes/, Configuration/, Tests/
- **Lower memory usage** - Optimized for smaller codebases
- **Single-threaded** - Better for smaller projects, easier debugging

**Configuration:**
```yaml
quality-tools:
  project:
    name: "vendor/extension-name"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "Classes/"
      - "Configuration/"
      - "Tests/"
    exclude:
      - "var/"
      - "vendor/"
      - ".build/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    fractor:
      enabled: true
      indentation: 2

    phpstan:
      enabled: true
      level: 8  # Higher level for extensions
      memory_limit: "512M"  # Lower memory for smaller projects

    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  output:
    verbosity: "normal"
    colors: true

  performance:
    parallel: false  # Single-threaded for better debugging
    cache_enabled: true
```

### TYPO3 Site Package Template

**Use Case:** Client projects using TYPO3 site packages

**Command:**
```bash
vendor/bin/qt config:init --template=typo3-site-package
```

**Key Differences:**
- **Site package paths** - packages/, config/
- **Moderate analysis level** - Balanced for client projects
- **Parallel processing** - Better performance for multi-package projects
- **Standard memory allocation** - 1G for typical site packages

**Configuration:**
```yaml
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
      - "vendor/"
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

  output:
    verbosity: "normal"
    colors: true
    progress: true

  performance:
    parallel: true
    max_processes: 4
    cache_enabled: true
```

### TYPO3 Distribution Template

**Use Case:** Large TYPO3 distributions with multiple sites and complex configurations

**Command:**
```bash
vendor/bin/qt config:init --template=typo3-distribution
```

**Key Differences:**
- **Distribution paths** - Includes config/sites/
- **Lower PHPStan level (5)** - More lenient for complex distributions
- **Higher memory allocation** - 2G for large codebases
- **More parallel processes** - 8 processes for better performance

**Configuration:**
```yaml
quality-tools:
  project:
    name: "enterprise-distribution"
    php_version: "8.3"
    typo3_version: "13.4"

  paths:
    scan:
      - "packages/"
      - "config/system/"
      - "config/sites/"
    exclude:
      - "var/"
      - "vendor/"
      - "public/"
      - "node_modules/"
      - ".build/"

  tools:
    rector:
      enabled: true
      level: "typo3-13"

    fractor:
      enabled: true
      indentation: 2

    phpstan:
      enabled: true
      level: 5  # More lenient for complex projects
      memory_limit: "2G"  # More memory for large projects

    php-cs-fixer:
      enabled: true
      preset: "typo3"

    typoscript-lint:
      enabled: true
      indentation: 2

  output:
    verbosity: "normal"
    colors: true
    progress: true

  performance:
    parallel: true
    max_processes: 8  # More processes for large projects
    cache_enabled: true
```

## Template Initialization

### Basic Initialization

```bash
# Initialize with default template
vendor/bin/qt config:init

# Initialize with specific template
vendor/bin/qt config:init --template=typo3-extension

# Force overwrite existing configuration
vendor/bin/qt config:init --template=typo3-site-package --force
```

### Project Name Detection

Templates automatically detect your project name from:

1. **composer.json** - Uses the `name` field
2. **Directory name** - Falls back to current directory name

**Example composer.json:**
```json
{
  "name": "vendor/my-awesome-extension",
  "type": "typo3-cms-extension"
}
```

**Generated template:**
```yaml
quality-tools:
  project:
    name: "vendor/my-awesome-extension"
```

## Customizing Templates

### Post-Initialization Customization

After initializing with a template, customize the configuration for your specific needs:

```bash
# Initialize base template
vendor/bin/qt config:init --template=typo3-extension

# Edit the generated file
nano .quality-tools.yaml
```

**Common customizations:**

1. **Adjust PHPStan level** for your project's maturity
2. **Add or remove scan paths** based on your project structure
3. **Configure tool-specific settings** for your workflow
4. **Set up environment variables** for flexible deployment

### Example Customizations

#### Legacy Project (Lower Standards)
```yaml
quality-tools:
  project:
    name: "legacy-project"

  tools:
    phpstan:
      level: 3  # Lower level for legacy code
    
    rector:
      enabled: false  # Skip modernization for now
```

#### High-Quality Extension (Stricter Standards)
```yaml
quality-tools:
  project:
    name: "premium-extension"

  tools:
    phpstan:
      level: 9  # Maximum strictness
      memory_limit: "1G"
    
    php-cs-fixer:
      preset: "typo3"
      cache: true
```

#### Multi-Site Distribution
```yaml
quality-tools:
  project:
    name: "multi-tenant-distribution"

  paths:
    scan:
      - "packages/"
      - "config/system/"
      - "config/sites/"
      - "custom/extensions/"

  performance:
    max_processes: 12  # High parallelism
    cache_enabled: true
```

## Template Comparison

### Analysis Level Comparison

| Template | PHPStan Level | Rationale |
|----------|---------------|-----------|
| Extension | 8 | Extensions should meet high quality standards |
| Site Package | 6 | Balanced approach for client projects |
| Distribution | 5 | More lenient for complex, legacy distributions |
| Default | 6 | Good starting point for most projects |

### Performance Settings Comparison

| Template | Parallel | Max Processes | Memory | Rationale |
|----------|----------|---------------|--------|-----------|
| Extension | No | 1 | 512M | Smaller projects, easier debugging |
| Site Package | Yes | 4 | 1G | Moderate parallelism for typical projects |
| Distribution | Yes | 8 | 2G | High performance for large projects |
| Default | Yes | 4 | 1G | Balanced settings |

### Path Configuration Comparison

| Template | Scan Paths | Exclude Paths |
|----------|------------|---------------|
| Extension | Classes/, Configuration/, Tests/ | vendor/, .build/ |
| Site Package | packages/, config/ | var/, vendor/, public/, node_modules/ |
| Distribution | packages/, config/system/, config/sites/ | var/, vendor/, public/, node_modules/, .build/ |
| Default | packages/, config/system/ | var/, vendor/, node_modules/ |

## Advanced Template Usage

### Environment-Aware Templates

Create templates that adapt to different environments:

```yaml
quality-tools:
  project:
    name: "${PROJECT_NAME:-template-project}"
    php_version: "${PHP_VERSION:-8.3}"

  tools:
    phpstan:
      level: "${PHPSTAN_LEVEL:-6}"
      memory_limit: "${PHPSTAN_MEMORY:-1G}"

  performance:
    parallel: "${ENABLE_PARALLEL:-true}"
    max_processes: "${MAX_PROCESSES:-4}"
```

**Usage:**
```bash
# Development environment
export PHPSTAN_LEVEL="5"
export PHPSTAN_MEMORY="512M"
export ENABLE_PARALLEL="false"

vendor/bin/qt config:init --template=typo3-extension

# CI environment
export PHPSTAN_LEVEL="8"
export PHPSTAN_MEMORY="2G"
export MAX_PROCESSES="8"

vendor/bin/qt config:init --template=typo3-extension
```

### Custom Path Templates

Adapt templates for non-standard project structures:

```bash
# Initialize template
vendor/bin/qt config:init --template=typo3-site-package

# Customize paths in .quality-tools.yaml
```

Edit the scan paths for your structure:
```yaml
quality-tools:
  paths:
    scan:
      - "src/"           # Custom source directory
      - "lib/"           # Custom library directory
      - "config/"        # Standard config
    exclude:
      - "var/"
      - "vendor/"
      - "legacy/"        # Exclude legacy code
      - "third-party/"   # Exclude third-party code
```

## Best Practices

### Choosing the Right Template

1. **TYPO3 Extension** - When developing extensions for public release
   - Higher quality standards
   - Extension-specific paths
   - Better for smaller codebases

2. **Site Package** - For client projects with custom site packages
   - Balanced quality standards
   - Client project structure
   - Moderate performance settings

3. **Distribution** - For large, complex TYPO3 installations
   - Multiple sites and configurations
   - High performance requirements
   - More lenient quality standards

4. **Default** - When unsure or for general projects
   - Good starting point
   - Easy to customize
   - Balanced settings

### Template Workflow

1. **Choose appropriate template** based on project type
2. **Initialize configuration** with template
3. **Validate configuration** to ensure it's correct
4. **Test tools** to verify they work as expected
5. **Customize as needed** for your specific requirements
6. **Document changes** for team members

```bash
# Complete workflow
vendor/bin/qt config:init --template=typo3-site-package
vendor/bin/qt config:validate
vendor/bin/qt lint:phpstan  # Test the configuration
# Edit .quality-tools.yaml as needed
vendor/bin/qt config:validate  # Validate changes
```

### Team Consistency

Use templates to ensure team consistency:

1. **Standardize on templates** across similar projects
2. **Document template choice** in project README
3. **Customize templates** for organization standards
4. **Version control** the final configuration

**Project README example:**
```markdown
## Quality Tools

This project uses the TYPO3 site package template:

```bash
vendor/bin/qt config:init --template=typo3-site-package
```

Customizations made:
- PHPStan level raised to 7 for higher code quality
- Added custom scan path for legacy integration
```

## Troubleshooting Templates

### Common Issues

1. **Wrong template for project type**
   ```bash
   # Solution: Re-initialize with correct template
   vendor/bin/qt config:init --template=typo3-extension --force
   ```

2. **Template doesn't match project structure**
   ```bash
   # Solution: Customize paths after initialization
   nano .quality-tools.yaml
   # Edit paths.scan and paths.exclude sections
   ```

3. **Performance issues with template settings**
   ```bash
   # For smaller projects: reduce parallelism
   # For larger projects: increase memory and processes
   ```

### Validation After Template Use

Always validate your configuration after initialization:

```bash
# Validate configuration
vendor/bin/qt config:validate

# Show resolved configuration
vendor/bin/qt config:show

# Test each tool
vendor/bin/qt lint:phpstan
vendor/bin/qt lint:rector
vendor/bin/qt lint:php-cs-fixer
```

## Future Template Development

The template system is designed to be extensible. Future versions may include:

- **Organization-specific templates** - Custom templates for specific organizations
- **Version-specific templates** - Templates for different TYPO3 versions
- **Framework integration templates** - Templates for specific frameworks or tools
- **Template repository** - Community-contributed templates

Templates provide a powerful way to get started quickly while maintaining flexibility for customization. Choose the template that best matches your project type and customize as needed for your specific requirements.