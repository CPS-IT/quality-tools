# Troubleshooting

This guide helps you diagnose and resolve common issues with CPSIT Quality Tools.

## Common Issues

### Installation Problems

#### Composer Autoloader Not Found

**Error:**
```
Error: Composer autoloader not found. Please run 'composer install'.
```

**Causes:**
- Composer dependencies not installed
- Running from wrong directory
- Corrupted vendor directory

**Solutions:**
```bash
# Install or update dependencies
composer install

# Clear and reinstall if corrupted
rm -rf vendor composer.lock
composer install

# Verify vendor directory structure
ls -la vendor/autoload.php
```

#### Binary Not Executable

**Error:**
```
bash: vendor/bin/qt: Permission denied
```

**Solutions:**
```bash
# Make binary executable
chmod +x vendor/bin/qt

# Check current permissions
ls -la vendor/bin/qt

# Expected output: -rwxr-xr-x ... vendor/bin/qt
```

#### Wrong PHP Version

**Error:**
```
Fatal error: This package requires PHP version 8.3 or higher
```

**Solutions:**
```bash
# Check your PHP version
php --version

# Use specific PHP version if multiple installed
/usr/bin/php8.3 vendor/bin/qt --version

# Update composer.json platform requirements
composer config platform.php 8.3
composer update
```

### Project Detection Issues

#### TYPO3 Project Root Not Found

**Error:**
```
TYPO3 project root not found. Please run this command from within a TYPO3 project directory, or set the QT_PROJECT_ROOT environment variable.
```

**Diagnosis Steps:**

1. **Verify Current Location:**
```bash
pwd
ls -la composer.json
```

2. **Check TYPO3 Dependencies:**
```bash
grep -E "typo3/(cms-core|cms|minimal)" composer.json
```

3. **Enable Debug Mode:**
```bash
QT_DEBUG=true vendor/bin/qt --version
```

**Solutions:**

1. **Run from TYPO3 Project Directory:**
```bash
# Navigate to project root
cd /path/to/your/typo3/project
vendor/bin/qt --version
```

2. **Add TYPO3 Dependencies:**
```bash
# Add TYPO3 core to composer.json
composer require typo3/cms-core:^13.4
```

3. **Use Environment Variable Override:**
```bash
export QT_PROJECT_ROOT=/path/to/your/typo3/project
vendor/bin/qt --version
```

#### Invalid composer.json

**Error:**
```
Failed to parse composer.json
```

**Diagnosis:**
```bash
# Validate JSON syntax
cat composer.json | python -m json.tool

# Or use composer to validate
composer validate
```

**Solutions:**
```bash
# Fix JSON syntax errors manually
# Use an IDE with JSON validation
# Or restore from backup/version control

git checkout composer.json
composer install
```

#### Permission Denied Reading composer.json

**Error:**
```
Permission denied: /path/to/composer.json
```

**Solutions:**
```bash
# Check file permissions
ls -la composer.json

# Fix permissions if needed
chmod 644 composer.json
sudo chown $USER:$USER composer.json
```

### Runtime Issues

#### Command Not Found

**Error:**
```
bash: qt: command not found
```

**For Local Installation:**
```bash
# Use full path
vendor/bin/qt --version

# Or add to PATH temporarily  
export PATH="$PWD/vendor/bin:$PATH"
qt --version
```

**For Global Installation:**
```bash
# Check global bin directory
composer global config bin-dir --absolute

# Add to PATH in shell profile
echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

#### Memory Limit Exceeded

**Error:**
```
Fatal error: Allowed memory size exhausted
```

**Solutions:**
```bash
# Increase PHP memory limit temporarily
php -d memory_limit=512M vendor/bin/qt --version

# Or set globally in php.ini
echo "memory_limit = 512M" >> /etc/php/8.3/cli/php.ini

# Check current limit
php -r "echo ini_get('memory_limit');"
```

#### Timeout Issues

**Error:**
```
Maximum execution time exceeded
```

**Solutions:**
```bash
# Increase time limit
php -d max_execution_time=300 vendor/bin/qt --version

# Disable time limit for CLI (careful!)
php -d max_execution_time=0 vendor/bin/qt --version
```

## Debugging Techniques

### Enable Debug Mode

Get detailed execution information:

```bash
# Enable debug output
export QT_DEBUG=true
vendor/bin/qt --version

# Or for single command
QT_DEBUG=true vendor/bin/qt --version
```

### Increase Verbosity

Use verbose options for more information:

```bash
# Level 1 verbose
vendor/bin/qt --version -v

# Level 2 verbose  
vendor/bin/qt --version -vv

# Level 3 debug
vendor/bin/qt --version -vvv
```

### Check System Requirements

Verify your environment meets all requirements:

```bash
# PHP version
php --version

# Required extensions
php -m | grep -E "(json|mbstring|xml)"

# Composer version
composer --version

# Available memory
php -r "echo 'Memory: ' . ini_get('memory_limit') . PHP_EOL;"
```

### Validate Project Structure

Check your TYPO3 project structure:

```bash
# Check for required files
ls -la composer.json
ls -la config/
ls -la vendor/

# Verify TYPO3 installation
ls -la vendor/typo3/

# Check composer dependencies
composer show | grep typo3
```

## Environment-Specific Issues

### Docker Containers

**Path Issues:**
```bash
# Container path might be different
docker exec container-name pwd
docker exec container-name ls -la /var/www/html/vendor/bin/qt

# Use correct internal path
docker exec container-name /var/www/html/vendor/bin/qt --version
```

**Permission Issues:**
```bash
# Fix ownership in container
docker exec container-name chown -R www-data:www-data /var/www/html/vendor/bin/qt
```

### Windows Environments

**Path Separator Issues:**
```cmd
REM Use Windows path format
set QT_PROJECT_ROOT=C:\xampp\htdocs\myproject
vendor\bin\qt --version

REM Use forward slashes in environment variables
set QT_PROJECT_ROOT=C:/xampp/htdocs/myproject
```

**Execution Policy Issues:**
```powershell
# Enable script execution in PowerShell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Or run with full PHP path
C:\xampp\php\php.exe vendor\bin\qt --version
```

### Symlink Issues

**Symlink Not Followed:**
```bash
# Check if symlinks are properly resolved
ls -la vendor/bin/qt
readlink vendor/bin/qt

# Use realpath to resolve
realpath vendor/bin/qt
```

## Getting Help

### Information Gathering

Before seeking help, gather this information:

```bash
# System information
uname -a
php --version
composer --version

# Project information  
pwd
ls -la composer.json
grep -E "typo3/(cms-core|cms|minimal)" composer.json

# Tool information
QT_DEBUG=true vendor/bin/qt --version 2>&1 | tee debug.log
```

### Debug Log Analysis

Common patterns in debug output:

```
# Successful detection
Project root detection started
Environment variable QT_PROJECT_ROOT: not set
Starting filesystem traversal from: /current/directory  
Found composer.json: /path/to/project/composer.json
TYPO3 dependencies found: typo3/cms-core
Project root confirmed: /path/to/project

# Failed detection
Project root detection started
Environment variable QT_PROJECT_ROOT: not set
Starting filesystem traversal from: /current/directory
No composer.json found after 10 levels
TYPO3 project root not found
```

### Recovery Procedures

#### Reset to Clean State

```bash
# Clean composer installation
rm -rf vendor composer.lock
composer install

# Reset permissions
find vendor -type f -exec chmod 644 {} \;
find vendor -type d -exec chmod 755 {} \;
chmod +x vendor/bin/*
```

#### Backup and Restore

```bash
# Backup current state
cp composer.json composer.json.bak
cp composer.lock composer.lock.bak

# Restore from backup
cp composer.json.bak composer.json
cp composer.lock.bak composer.lock
composer install
```

## Performance Issues

### Slow Project Detection

**Causes:**
- Very deep directory structure
- Slow filesystem (network drives)
- Many composer.json files to check

**Solutions:**
```bash
# Use environment variable to skip detection
export QT_PROJECT_ROOT=/path/to/project

# Run from project root to minimize traversal
cd /path/to/project
vendor/bin/qt --version
```

### Memory Usage

**Monitor memory usage:**
```bash
# Check memory usage during execution
/usr/bin/time -v vendor/bin/qt --version

# Or with simple monitoring
ps aux | grep php
```

## Known Limitations

### Current Version Limitations

1. **Limited Commands**: Only basic help and version commands available
2. **No Configuration Files**: Only environment variable configuration
3. **Single Project**: Cannot handle multiple TYPO3 projects simultaneously
4. **Limited Error Recovery**: Minimal automatic error recovery

### Planned Improvements

- Enhanced error messages
- Configuration file support
- Multiple project handling
- Automatic error recovery
- Better Windows support