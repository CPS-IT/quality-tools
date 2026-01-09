<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Service\SecurityService
 */
final class SecurityServiceTest extends TestCase
{
    private SecurityService $securityService;

    protected function setUp(): void
    {
        $this->securityService = new SecurityService();
    }

    /**
     * @test
     */
    public function isEnvironmentVariableAllowedReturnsTrueForAllowlistedVariables(): void
    {
        $allowedVars = [
            'HOME',
            'USER',
            'QT_PROJECT_ROOT',
            'PHP_MEMORY_LIMIT',
            'CI',
            'PHPSTAN_CONFIG_PATH',
        ];

        foreach ($allowedVars as $var) {
            self::assertTrue(
                $this->securityService->isEnvironmentVariableAllowed($var),
                "Variable '{$var}' should be allowed",
            );
        }
    }

    /**
     * @test
     */
    public function isEnvironmentVariableAllowedReturnsFalseForDisallowedVariables(): void
    {
        $disallowedVars = [
            'PATH',
            'SECRET_KEY',
            'DATABASE_PASSWORD',
            'AWS_SECRET_ACCESS_KEY',
            'SSH_PRIVATE_KEY',
            'ADMIN_TOKEN',
        ];

        foreach ($disallowedVars as $var) {
            self::assertFalse(
                $this->securityService->isEnvironmentVariableAllowed($var),
                "Variable '{$var}' should not be allowed",
            );
        }
    }

    /**
     * @test
     */
    public function isEnvironmentVariableAllowedReturnsFalseForInvalidVariableNames(): void
    {
        $invalidNames = [
            'lowercase',
            '123INVALID',
            'SPECIAL-CHARS',
            'SPACE VAR',
            'WITH.DOT',
            '',
        ];

        foreach ($invalidNames as $name) {
            self::assertFalse(
                $this->securityService->isEnvironmentVariableAllowed($name),
                "Invalid variable name '{$name}' should not be allowed",
            );
        }
    }

    /**
     * @test
     */
    public function getEnvironmentVariableReturnsValueForAllowedVariable(): void
    {
        // Set a test environment variable
        putenv('QT_PROJECT_ROOT=/test/path');
        $_ENV['QT_PROJECT_ROOT'] = '/test/path';

        $result = $this->securityService->getEnvironmentVariable('QT_PROJECT_ROOT');

        self::assertSame('/test/path', $result);

        // Cleanup
        putenv('QT_PROJECT_ROOT');
        unset($_ENV['QT_PROJECT_ROOT']);
    }

    /**
     * @test
     */
    public function getEnvironmentVariableReturnsDefaultForMissingVariable(): void
    {
        // Use an allowed variable that doesn't exist
        $result = $this->securityService->getEnvironmentVariable('QT_DEBUG_TEMP_FILES', 'default_value');

        self::assertSame('default_value', $result);
    }

    /**
     * @test
     */
    public function getEnvironmentVariableThrowsExceptionForDisallowedVariable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access to environment variable "PATH" is not allowed for security reasons');

        $this->securityService->getEnvironmentVariable('PATH');
    }

    /**
     * @test
     */
    public function getEnvironmentVariableThrowsExceptionForDangerousContent(): void
    {
        // Set a dangerous environment variable value
        putenv('QT_PROJECT_ROOT=../../../etc/passwd');
        $_ENV['QT_PROJECT_ROOT'] = '../../../etc/passwd';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Environment variable "QT_PROJECT_ROOT" contains potentially unsafe content');

        try {
            $this->securityService->getEnvironmentVariable('QT_PROJECT_ROOT');
        } finally {
            // Cleanup
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT']);
        }
    }

    /**
     * @test
     *
     * @dataProvider dangerousContentProvider
     */
    public function getEnvironmentVariableRejectsDangerousContent(string $dangerousValue, string $description): void
    {
        putenv('QT_PROJECT_ROOT=' . $dangerousValue);
        $_ENV['QT_PROJECT_ROOT'] = $dangerousValue;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contains potentially unsafe content');

        try {
            $this->securityService->getEnvironmentVariable('QT_PROJECT_ROOT');
        } finally {
            // Cleanup
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT']);
        }
    }

    public static function dangerousContentProvider(): array
    {
        return [
            ['../../../etc/passwd', 'Directory traversal'],
            ['${OTHER_VAR}', 'Variable expansion'],
            ['$(cat /etc/passwd)', 'Command substitution'],
            ['`whoami`', 'Backtick execution'],
            ['path | rm -rf /', 'Pipe to dangerous command'],
            ['/path > /etc/passwd', 'Redirect to system file'],
            ["null\x00byte", 'Null byte injection'],
            ["\x01control", 'Control character'],
        ];
    }

    /**
     * @test
     */
    public function getAllowedEnvironmentVariablesReturnsCorrectList(): void
    {
        $allowed = $this->securityService->getAllowedEnvironmentVariables();

        self::assertIsArray($allowed);
        self::assertContains('HOME', $allowed);
        self::assertContains('QT_PROJECT_ROOT', $allowed);
        self::assertContains('PHP_MEMORY_LIMIT', $allowed);
        self::assertNotContains('PATH', $allowed);
        self::assertNotContains('SECRET_KEY', $allowed);
    }

    /**
     * @test
     */
    public function hasSecureFilePermissionsReturnsTrueForSecureFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'security_test_');
        chmod($tempFile, 0o600);

        $result = $this->securityService->hasSecureFilePermissions($tempFile);

        self::assertTrue($result);

        unlink($tempFile);
    }

    /**
     * @test
     */
    public function hasSecureFilePermissionsReturnsFalseForInsecureFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'security_test_');
        chmod($tempFile, 0o644); // World-readable

        $result = $this->securityService->hasSecureFilePermissions($tempFile);

        self::assertFalse($result);

        unlink($tempFile);
    }

    /**
     * @test
     */
    public function hasSecureFilePermissionsReturnsFalseForNonexistentFile(): void
    {
        $result = $this->securityService->hasSecureFilePermissions('/nonexistent/file');

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function setSecureFilePermissionsSetsCorrectPermissions(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'security_test_');
        chmod($tempFile, 0o644); // Start with insecure permissions

        $this->securityService->setSecureFilePermissions($tempFile);

        $permissions = fileperms($tempFile) & 0o777;
        self::assertSame(0o600, $permissions);

        unlink($tempFile);
    }

    /**
     * @test
     */
    public function setSecureFilePermissionsThrowsExceptionForNonexistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File does not exist');

        $this->securityService->setSecureFilePermissions('/nonexistent/file');
    }
}
