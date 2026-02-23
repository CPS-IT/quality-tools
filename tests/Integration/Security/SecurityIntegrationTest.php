<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Security;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Service\DisposableTemporaryFile;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration tests for security hardening features.
 */
final class SecurityIntegrationTest extends TestCase
{
    private string $tempDir;
    private SecurityService $securityService;
    private FilesystemService $filesystemService;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('security_integration_test_');
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(new Filesystem(), $this->securityService);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * @test
     */
    public function configurationLoadingRejectsDisallowedEnvironmentVariables(): void
    {
        // Create a configuration that tries to access a disallowed environment variable
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "test-\${PATH}"  # PATH is not allowed
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configContent);

        $loader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access to environment variable "PATH" is not allowed for security reasons');

        $loader->load($this->tempDir);
    }

    /**
     * @test
     */
    public function configurationLoadingAllowsAllowlistedEnvironmentVariables(): void
    {
        // Set an allowed environment variable
        putenv('QT_PROJECT_ROOT=' . $this->tempDir);
        $_ENV['QT_PROJECT_ROOT'] = $this->tempDir;

        // Create a configuration that uses an allowed environment variable in project name
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "test-\${QT_PROJECT_ROOT}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configContent);

        $loader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
        );
        $config = $loader->load($this->tempDir);

        $data = $config->toArray();
        self::assertArrayHasKey('quality-tools', $data);
        self::assertArrayHasKey('project', $data['quality-tools']);
        self::assertArrayHasKey('name', $data['quality-tools']['project']);
        self::assertStringContainsString($this->tempDir, $data['quality-tools']['project']['name']);

        // Cleanup
        putenv('QT_PROJECT_ROOT');
        unset($_ENV['QT_PROJECT_ROOT']);
    }

    /**
     * @test
     */
    public function configurationLoadingUsesDefaultForMissingAllowedVariable(): void
    {
        // Create a configuration that uses an allowed but unset environment variable with default in project name
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "test-\${PHP_MEMORY_LIMIT:-1G}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configContent);

        $loader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
        );
        $config = $loader->load($this->tempDir);

        $data = $config->toArray();
        self::assertArrayHasKey('quality-tools', $data);
        self::assertArrayHasKey('project', $data['quality-tools']);
        self::assertArrayHasKey('name', $data['quality-tools']['project']);
        self::assertSame('test-1G', $data['quality-tools']['project']['name']);
    }

    /**
     * @test
     */
    public function configurationLoadingRejectsDangerousEnvironmentVariableContent(): void
    {
        // Set an allowed environment variable with dangerous content
        putenv('QT_PROJECT_ROOT=../../../etc/passwd');
        $_ENV['QT_PROJECT_ROOT'] = '../../../etc/passwd';

        $configContent = <<<YAML
            quality-tools:
              project:
                name: "test-\${QT_PROJECT_ROOT}"
            YAML;

        file_put_contents($this->tempDir . '/.quality-tools.yaml', $configContent);

        $loader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Environment variable "QT_PROJECT_ROOT" contains potentially unsafe content');

        try {
            $loader->load($this->tempDir);
        } finally {
            // Cleanup
            putenv('QT_PROJECT_ROOT');
            unset($_ENV['QT_PROJECT_ROOT']);
        }
    }

    /**
     * @test
     */
    public function temporaryFilesHaveSecurePermissions(): void
    {
        $tempFile = new DisposableTemporaryFile(
            $this->securityService,
            $this->filesystemService,
            'security_test_',
            '.tmp',
        );
        $path = $tempFile->getPath();

        // Check that the file exists and has secure permissions
        self::assertFileExists($path);

        $permissions = fileperms($path) & 0o777;
        self::assertSame(0o600, $permissions, 'Temporary file should have 0600 permissions');

        $tempFile->cleanup();
        self::assertFileDoesNotExist($path);
    }

    /**
     * @test
     */
    public function securityServiceBlocksCommonAttackVectors(): void
    {
        $securityService = new SecurityService();

        // Test various attack vectors
        $attackVectors = [
            'PATH' => 'System path access',
            'SECRET_KEY' => 'Secret key access',
            'DATABASE_PASSWORD' => 'Database credential access',
            'AWS_SECRET_ACCESS_KEY' => 'Cloud credential access',
            'SSH_PRIVATE_KEY' => 'SSH key access',
            'ADMIN_TOKEN' => 'Admin token access',
        ];

        foreach ($attackVectors as $envVar => $description) {
            self::assertFalse(
                $securityService->isEnvironmentVariableAllowed($envVar),
                "Security service should block {$description} via {$envVar}",
            );
        }
    }

    /**
     * @test
     */
    public function securityServiceAllowsOnlyNecessaryVariables(): void
    {
        $securityService = new SecurityService();
        $allowed = $securityService->getAllowedEnvironmentVariables();

        // Verify that only essential variables are allowed
        $essentialCategories = [
            'HOME', 'USER', 'USERNAME', // User info
            'QT_PROJECT_ROOT', 'QT_VENDOR_DIR', // Quality tools specific
            'PHP_MEMORY_LIMIT', 'PHP_VERSION', // PHP configuration
            'CI', 'GITHUB_ACTIONS', // CI/CD indicators
        ];

        foreach ($essentialCategories as $var) {
            self::assertContains($var, $allowed, "Essential variable {$var} should be allowed");
        }

        // Verify dangerous variables are not in the allowlist
        $dangerousVars = ['PATH', 'LD_LIBRARY_PATH', 'SHELL', 'SUDO_USER'];
        foreach ($dangerousVars as $var) {
            self::assertNotContains($var, $allowed, "Dangerous variable {$var} should not be allowed");
        }
    }
}
