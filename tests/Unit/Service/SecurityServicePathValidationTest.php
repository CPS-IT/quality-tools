<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Exception\SecurityException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for SecurityService path validation functionality.
 */
final class SecurityServicePathValidationTest extends TestCase
{
    private SecurityService $securityService;
    private FilesystemService $filesystemService;
    private string $tempDir;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('security_path_test_');
        $this->projectRoot = $this->tempDir;

        $this->securityService = new SecurityService();
        $filesystem = new Filesystem();
        $this->filesystemService = new FilesystemService($filesystem, $this->securityService);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function sanitizePathThrowsExceptionForDirectoryTraversal(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionCode(SecurityException::ERROR_UNSAFE_PATH_CONTENT);
        $this->expectExceptionMessage('Path contains potentially unsafe content');

        $this->securityService->sanitizePath('../../../etc/passwd');
    }

    /**
     * @test
     */
    public function sanitizePathThrowsExceptionForNullByte(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionCode(SecurityException::ERROR_UNSAFE_PATH_CONTENT);

        $this->securityService->sanitizePath("config.php\0.txt");
    }

    /**
     * @test
     */
    public function sanitizePathThrowsExceptionForPipeCommand(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionCode(SecurityException::ERROR_UNSAFE_PATH_CONTENT);

        $this->securityService->sanitizePath('config.php | cat');
    }

    public static function validToolConfigurationsProvider(): array
    {
        return [
            'rector php config' => ['rector', 'php', '<?php return [];'],
            'phpstan neon config' => ['phpstan', 'neon', 'parameters: level: 6'],
            'phpstan neon.dist config' => ['phpstan', 'neon.dist', 'parameters: level: 6'],
            'fractor php config' => ['fractor', 'php', '<?php return [];'],
            'php-cs-fixer php config' => ['php-cs-fixer', 'php', '<?php return [];'],
            'typoscript-lint yml config' => ['typoscript-lint', 'yml', 'paths: [.]'],
            'typoscript-lint yaml config' => ['typoscript-lint', 'yaml', 'paths: [.]'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider dangerousPathsProvider
     */
    public function sanitizePathRejectsDangerousPaths(string $dangerousPath): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionCode(SecurityException::ERROR_UNSAFE_PATH_CONTENT);

        $this->securityService->sanitizePath($dangerousPath);
    }

    public static function dangerousPathsProvider(): array
    {
        return [
            'directory traversal' => ['../../../etc/passwd'],
            'windows traversal' => ['..\\..\\windows\\system32'],
            'null byte injection' => ["config.php\0.txt"],
            'command substitution' => ['$(rm -rf /)'],
            'backtick execution' => ['`cat /etc/passwd`'],
            'pipe command' => ['config.php | cat'],
            'redirect output' => ['config.php > /tmp/evil'],
            'variable expansion' => ['${HOME}/.ssh/id_rsa'],
        ];
    }
}
