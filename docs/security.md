# Security Hardening Documentation

This document describes the security measures implemented in the Quality Tools package to protect against common security vulnerabilities.

## Environment Variable Security

### Overview

The configuration system supports environment variable interpolation using `${VAR}` or `${VAR:-default}` syntax in YAML files. To prevent security risks, only allowlisted environment variables are accessible.

### Allowlisted Environment Variables

The following environment variables are permitted in configuration files:

**User and System Information:**
- `HOME` - User home directory
- `USER` - Current username
- `USERNAME` - Alternative username variable

**Quality Tools Specific:**
- `QT_PROJECT_ROOT` - Project root directory
- `QT_VENDOR_DIR` - Vendor directory path
- `QT_DEBUG_TEMP_FILES` - Debug flag for temporary file logging
- `QT_DYNAMIC_PATHS` - Dynamic path configuration

**PHP Configuration:**
- `PHP_MEMORY_LIMIT` - PHP memory limit setting
- `PHP_VERSION` - PHP version string
- `PHP_BINARY` - Path to PHP binary

**CI/CD Environment Indicators:**
- `CI` - Generic CI flag
- `GITHUB_ACTIONS` - GitHub Actions environment
- `GITLAB_CI` - GitLab CI environment
- `JENKINS_URL` - Jenkins environment
- `TRAVIS` - Travis CI environment
- `CIRCLECI` - CircleCI environment

**Build and Deployment:**
- `PROJECT_ROOT` - Project root directory
- `BUILD_DIR` - Build output directory
- `VENDOR_DIR` - Vendor dependencies directory

**Tool Configuration Paths:**
- `PHPSTAN_CONFIG_PATH` - PHPStan configuration file path
- `RECTOR_CONFIG_PATH` - Rector configuration file path
- `PHP_CS_FIXER_CONFIG_PATH` - PHP CS Fixer configuration file path
- `FRACTOR_CONFIG_PATH` - Fractor configuration file path

### Security Validation

Environment variable values are validated for the following security concerns:

1. **Null byte injection** - Values containing null bytes are rejected
2. **Control characters** - Values containing control characters are rejected
3. **Directory traversal** - Values containing `../` patterns are rejected
4. **Command injection** - Values containing command substitution patterns are rejected
5. **Variable expansion** - Nested variable expansion is not allowed
6. **Command execution** - Backtick command execution patterns are rejected
7. **Shell operations** - Pipe and redirect patterns are rejected

### Usage Examples

**Safe usage:**
```yaml
quality-tools:
  project:
    name: "${PROJECT_ROOT}"
  tools:
    phpstan:
      memory_limit: "${PHP_MEMORY_LIMIT:-1G}"
```

**Blocked usage:**
```yaml
quality-tools:
  project:
    # This will fail - PATH is not allowlisted
    path: "${PATH}"
    # This will fail - dangerous content
    config: "${QT_PROJECT_ROOT}/../../../etc/passwd"
```

## Temporary File Security

### Overview

Temporary files created by the quality tools are secured with proper permissions to prevent unauthorized access in multi-user environments.

### Security Measures

1. **Secure Permissions**: All temporary files are created with 0600 permissions (readable/writable by owner only)
2. **Unpredictable Names**: Files use PHP's `tempnam()` function for cryptographically secure random naming
3. **Automatic Cleanup**: Files are automatically cleaned up via destructors and shutdown handlers
4. **Permission Validation**: File permissions are validated after creation

### Implementation

```php
use Cpsit\QualityTools\Service\DisposableTemporaryFile;

// Creates a temporary file with secure permissions
$tempFile = new DisposableTemporaryFile('prefix_', '.extension');
$tempFile->write('content');
// File is automatically cleaned up when object is destroyed
```

### Debug Logging

Set `QT_DEBUG_TEMP_FILES=1` environment variable to enable debug logging of temporary file operations:

```bash
QT_DEBUG_TEMP_FILES=1 vendor/bin/qt lint:phpstan
```

## Security Best Practices

### For Users

1. **Environment Variables**: Only use allowlisted environment variables in configuration files
2. **File Permissions**: Ensure proper file permissions on configuration files (0644 or stricter)
3. **Sensitive Data**: Never store secrets or credentials in environment variables used by quality tools
4. **Configuration Validation**: Regularly validate configuration files for security issues

### For Developers

1. **Input Validation**: All user input and configuration values are validated
2. **Principle of Least Privilege**: Only necessary environment variables are accessible
3. **Secure Defaults**: All security features are enabled by default
4. **Error Handling**: Security errors provide clear messages without exposing sensitive information

### For System Administrators

1. **Environment Isolation**: Run quality tools in isolated environments when possible
2. **File System Security**: Ensure temporary directories have appropriate permissions
3. **Process Isolation**: Consider running quality tools under restricted user accounts
4. **Monitoring**: Monitor for security-related error messages in logs

## Security Testing

The package includes comprehensive security tests:

- **Unit tests** for security service functionality
- **Integration tests** for configuration security
- **Attack vector tests** for common security vulnerabilities
- **Permission validation tests** for temporary file security

Run security tests:
```bash
vendor/bin/phpunit tests/Unit/Service/SecurityServiceTest.php
vendor/bin/phpunit tests/Integration/Security/SecurityIntegrationTest.php
```

## Reporting Security Issues

If you discover a security vulnerability in the Quality Tools package:

1. **Do not** create a public issue or pull request
2. **Do not** discuss the vulnerability in public channels
3. Contact the maintainers directly through private channels
4. Provide detailed information about the vulnerability and potential impact
5. Allow reasonable time for the issue to be addressed before public disclosure

## Security Compliance

This package implements security measures to help meet compliance requirements:

- **Input validation** and sanitization
- **Secure file handling** with proper permissions
- **Environment variable protection** against injection attacks
- **Comprehensive logging** for security auditing
- **Security testing** coverage for all security features

The security implementation follows industry best practices and is regularly updated to address emerging threats.