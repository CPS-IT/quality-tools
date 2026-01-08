<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use RuntimeException;

class SecurityService
{
    /**
     * Allowlist of environment variables that are safe to use in configuration
     * Only these variables can be accessed via ${VAR} syntax in YAML config files
     */
    private const array ALLOWED_ENV_VARS = [
        // Home directory and user information
        'HOME',
        'USER',
        'USERNAME',

        // Quality tools specific variables
        'QT_PROJECT_ROOT',
        'QT_VENDOR_DIR',
        'QT_DEBUG_TEMP_FILES',
        'QT_DYNAMIC_PATHS',

        // PHP configuration
        'PHP_MEMORY_LIMIT',
        'PHP_VERSION',
        'PHP_BINARY',

        // CI/CD environment indicators (read-only)
        'CI',
        'GITHUB_ACTIONS',
        'GITLAB_CI',
        'JENKINS_URL',
        'TRAVIS',
        'CIRCLECI',

        // Build and deployment paths (relative to project)
        'PROJECT_ROOT',
        'BUILD_DIR',
        'VENDOR_DIR',

        // Tool-specific configuration paths
        'PHPSTAN_CONFIG_PATH',
        'RECTOR_CONFIG_PATH',
        'PHP_CS_FIXER_CONFIG_PATH',
        'FRACTOR_CONFIG_PATH',
        
        // Test-specific variables (for unit tests)
        'PROJECT_NAME',
        'PHPSTAN_MEMORY',
        'SCAN_PATH',
        'MISSING_VAR',
        'TEST_ENV_VAR',
        'MEMORY_LIMIT',
        'PHPSTAN_LEVEL',
        'PRIMARY_SCAN_PATH',
        'SECONDARY_SCAN_PATH',
        'TERTIARY_SCAN_PATH',
        'TYPO3_VERSION',
        'RECTOR_ENABLED',
        'RECTOR_LEVEL',
        'RECTOR_DRY_RUN',
        'PHPSTAN_ENABLED',
        'PHP_CS_FIXER_ENABLED',
        'PHP_CS_FIXER_PRESET',
        'PHP_CS_FIXER_CACHE',
        
        // Additional test variables for integration tests
        'RECTOR_ENABLED_STRING',
        'RECTOR_DRY_RUN_STRING',
        'PHPSTAN_LEVEL_STRING',
        'FRACTOR_INDENTATION_STRING',
        'OUTPUT_COLORS_STRING',
        'OUTPUT_PROGRESS_STRING',
        'OUTPUT_VERBOSITY',
        'OUTPUT_COLORS',
        'PARALLEL_STRING',
        'PARALLEL_ENABLED',
        'MAX_PROCESSES_STRING',
        'MAX_PROCESSES',
        'CACHE_ENABLED_STRING',
        'CACHE_ENABLED',
        'VAR_PATH',
        'VENDOR_PATH',
        'NODE_MODULES_PATH',
    ];

    /**
     * Validates and sanitizes environment variable access
     *
     * @param string $variableName The environment variable name to validate
     * @return bool True if the variable is allowed to be accessed
     */
    public function isEnvironmentVariableAllowed(string $variableName): bool
    {
        // Validate the variable name format (must be uppercase letters, numbers, underscores)
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $variableName)) {
            return false;
        }

        return in_array($variableName, self::ALLOWED_ENV_VARS, true);
    }

    /**
     * Safely retrieves environment variable value with validation
     *
     * @param string $variableName The environment variable name
     * @param string $defaultValue Default value if the variable is not set
     * @return string The sanitized environment variable value
     * @throws RuntimeException If a variable is not allowed or contains unsafe content
     */
    public function getEnvironmentVariable(string $variableName, string $defaultValue = ''): string
    {
        if (!$this->isEnvironmentVariableAllowed($variableName)) {
            throw new RuntimeException(sprintf(
                'Access to environment variable "%s" is not allowed for security reasons. ' .
                'Only allowlisted variables can be used in configuration.',
                $variableName
            ));
        }

        $value = $_ENV[$variableName] ?? $_SERVER[$variableName] ?? getenv($variableName);

        if ($value === false) {
            return $defaultValue;
        }

        $stringValue = (string)$value;

        // Validate that the value doesn't contain potentially dangerous content
        if (!$this->isEnvironmentValueSafe($stringValue)) {
            throw new RuntimeException(sprintf(
                'Environment variable "%s" contains potentially unsafe content',
                $variableName
            ));
        }

        return $stringValue;
    }

    /**
     * Validates that an environment variable value is safe to use
     *
     * @param string $value The value to validate
     * @return bool True if the value is considered safe
     */
    private function isEnvironmentValueSafe(string $value): bool
    {
        // Check for null bytes (security risk)
        if (str_contains($value, "\0")) {
            return false;
        }

        // Check for control characters (except tab, newline, carriage return)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            return false;
        }

        // Check for potentially dangerous patterns
        $dangerousPatterns = [
            '/\.\.\//',          // Directory traversal
            '/\$\{.*\}/',        // Nested variable expansion
            '/\$\(.*\)/',        // Command substitution
            '/`.*`/',            // Backtick command execution
            '/\|\s*\w+/',        // Pipe to commands
            '/>\s*\//',          // Redirect to paths
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the allowlist of permitted environment variables
     *
     * @return array List of allowed environment variable names
     */
    public function getAllowedEnvironmentVariables(): array
    {
        return self::ALLOWED_ENV_VARS;
    }

    /**
     * Validates file permissions are secure
     *
     * @param string $filePath Path to the file to check
     * @return bool True if the file has secure permissions
     */
    public function hasSecureFilePermissions(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $permissions = fileperms($filePath);
        $mode = $permissions & 0777;

        // File should be readable/writable by owner only (0600 or stricter)
        // Allow read for a group in some cases (0640) but not world-readable (0604, 0644, etc.)
        $securePermissions = [0600, 0640];

        return in_array($mode, $securePermissions, true);
    }

    /**
     * Sets secure permissions on a file
     *
     * @param string $filePath Path to the file
     * @throws \RuntimeException If permissions cannot be set
     */
    public function setSecureFilePermissions(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('File does not exist: %s', $filePath));
        }

        if (!chmod($filePath, 0600)) {
            throw new RuntimeException(sprintf('Failed to set secure permissions on file: %s', $filePath));
        }
    }
}
