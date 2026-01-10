# Developer Guide

This guide provides comprehensive documentation for developers working on the CPSIT Quality Tools project, including architecture, testing practices, and contribution guidelines.

## Overview

The CPSIT Quality Tools package is a comprehensive CLI tool for TYPO3 projects that provides standardized quality assurance workflows. It includes preconfigured tools for code analysis, style checking, and modernization.

## Architecture

### Core Components

- **Configuration System**: Unified YAML-based configuration with JSON Schema validation
- **CLI Commands**: Symfony Console-based commands for tool execution
- **Tool Integrations**: Wrappers for Rector, PHPStan, PHP CS Fixer, Fractor, and TypoScript Lint
- **Path Resolution**: Dynamic path scanning with glob pattern support
- **Resource Optimization**: Automatic performance tuning based on project size

### Project Structure

```
src/
├── Configuration/           # Configuration management
│   ├── Configuration.php    # Main configuration class
│   ├── ConfigurationValidator.php  # JSON Schema validation
│   ├── YamlConfigurationLoader.php # YAML file loading
│   └── ValidationResult.php # Validation result handling
├── Console/
│   └── Command/            # CLI command implementations
├── Exception/              # Custom exception classes
├── Service/                # Business logic services
└── Utility/                # Helper utilities
```

### Configuration Architecture

The configuration system supports multiple layers:

1. **Package Defaults**: Built-in sensible defaults
2. **Global User Config**: User-specific settings
3. **Project Config**: Project-specific YAML configuration
4. **CLI Overrides**: Command-line parameter overrides

Configuration is validated against a JSON Schema to ensure correctness and provide clear error messages.

## Development Environment

### Requirements

- PHP 8.3+
- Composer
- All development dependencies defined in composer.json

### Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code quality
composer lint
composer sca:php

# Fix code style
composer fix
```

### Code Quality Standards

The project maintains high code quality standards:

- **PHPStan Level 6**: Strict static analysis
- **PHP CS Fixer**: TYPO3 coding standards
- **Test Coverage**: High coverage with unit and integration tests
- **Architecture Testing**: Ensures proper dependency management

## Testing

### Testing Philosophy

- **Comprehensive Coverage**: Both unit and integration tests
- **Test Isolation**: Each test runs independently
- **Real-world Scenarios**: Integration tests simulate actual usage
- **Performance Testing**: Ensures optimization features work correctly

### Test Structure

```
tests/
├── Unit/                   # Isolated unit tests
│   ├── Configuration/      # Configuration system tests
│   ├── Console/           # CLI command tests
│   └── Service/           # Service layer tests
└── Integration/           # End-to-end integration tests
    ├── Configuration/     # Configuration workflow tests
    └── Console/          # Command integration tests
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:integration

# Run with coverage
composer test:coverage
```

See [Testing Infrastructure](testing.md) for detailed testing practices and guidelines.

## Contributing

### Code Style

- Follow TYPO3 coding standards
- Use strict types declaration
- Provide comprehensive PHPDoc comments
- Write meaningful commit messages

### Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Implement changes with tests
4. Ensure all quality checks pass
5. Submit pull request with clear description

### Development Workflow

1. **Issue Analysis**: Understand requirements and impact
2. **Design**: Plan implementation approach
3. **Implementation**: Write code with tests
4. **Quality Assurance**: Run all checks
5. **Documentation**: Update relevant documentation

## Architecture Patterns

### Dependency Injection

The project uses Symfony's dependency injection container for service management. Services are defined in `config/services.yaml`.

### Command Pattern

CLI commands follow Symfony Console patterns with proper input validation and error handling.

### Strategy Pattern

Tool-specific implementations follow strategy patterns for flexibility and extensibility.

### Factory Pattern

Configuration and service creation uses factory patterns for complex object construction.

## Extension Points

### Adding New Tools

1. Create tool-specific configuration class
2. Implement command wrapper
3. Add JSON Schema validation rules
4. Write comprehensive tests
5. Update documentation

### Custom Path Resolvers

The path scanning system supports custom resolvers for complex project structures.

### Custom Validators

Additional validation rules can be added to the JSON Schema or as custom validators.

## Performance Considerations

### Optimization Features

- **Automatic Project Analysis**: Analyzes project size and complexity
- **Dynamic Memory Management**: Adjusts tool memory limits based on project size
- **Parallel Processing**: Utilizes multiple cores when beneficial
- **Intelligent Caching**: Caches results for faster subsequent runs

### Profiling

Use Xdebug or other profiling tools to identify performance bottlenecks:

```bash
# Run with profiling
XDEBUG_MODE=profile vendor/bin/qt lint:phpstan
```

## Documentation

### Writing Documentation

- Use clear, concise language
- Provide practical examples
- Include troubleshooting guidance
- Keep documentation up-to-date with code changes

### Documentation Structure

- **User Guide**: End-user facing documentation
- **Developer Guide**: Technical implementation details
- **API Documentation**: Generated from code comments

## Troubleshooting

### Common Issues

- **Memory Limits**: Adjust tool-specific memory settings
- **Path Resolution**: Check glob patterns and exclusions
- **Configuration Validation**: Use schema validation for debugging
- **Performance**: Enable optimization features

### Debugging

```bash
# Verbose output for debugging
vendor/bin/qt --verbose lint:phpstan

# Configuration debugging
vendor/bin/qt config:validate --verbose

# Path resolution debugging
vendor/bin/qt config:show --verbose
```

## Release Process

1. Update version numbers
2. Update CHANGELOG.md
3. Run full test suite
4. Create release tag
5. Update documentation
6. Publish release notes

## Resources

- [Testing Infrastructure](testing.md) - Comprehensive testing guide
- [User Guide](../user-guide/index.md) - End-user documentation
- [Configuration Reference](../configuration/reference.md) - Complete configuration options
- [Migration Guide](../configuration/migration.md) - Version upgrade guidance

## Getting Help

- **Issues**: Report bugs and feature requests on GitHub
- **Discussions**: Join community discussions for questions
- **Documentation**: Check existing documentation first
- **Code Review**: Submit pull requests for community review