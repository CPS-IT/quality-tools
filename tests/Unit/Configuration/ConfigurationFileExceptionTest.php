<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Exception\ConfigurationFileNotFoundException;
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ConfigurationFileNotFoundException::class)]
#[CoversClass(ConfigurationFileNotReadableException::class)]
#[CoversClass(ConfigurationLoadException::class)]
final class ConfigurationFileExceptionTest extends TestCase
{
    private ConfigurationLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_exception_test_');
        $this->loader = new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(
                new Filesystem(),
                new SecurityService(),
            ),
            new ToolConfigurationValidationService(),
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function defaultConfigurationUsedIfFileNotFound(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir);

        $config = $this->loader->load($emptyDir);

        self::assertSame('8.3', $config->getProjectPhpVersion());
    }

    public function testFileValidationPreventsPHPWarnings(): void
    {
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permission tests are meaningless when running as root');
        }

        $unreadableFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($unreadableFile, 'quality-tools: {}');
        chmod($unreadableFile, 0o000);

        try {
            $this->expectException(ConfigurationFileNotReadableException::class);
            $this->expectExceptionMessage('File exists but is not readable');

            $this->loader->load($this->tempDir);
        } finally {
            if (file_exists($unreadableFile)) {
                chmod($unreadableFile, 0o644);
            }
        }
    }

    public function testExceptionMessageFormatting(): void
    {
        $testFile = '/test/path/config.yaml';

        $notFoundException = new ConfigurationFileNotFoundException($testFile);
        self::assertStringContainsString('Configuration file error', $notFoundException->getMessage());
        self::assertStringContainsString($testFile, $notFoundException->getMessage());
        self::assertStringContainsString('File not found', $notFoundException->getMessage());

        $notReadableException = new ConfigurationFileNotReadableException($testFile);
        self::assertStringContainsString('Configuration file error', $notReadableException->getMessage());
        self::assertStringContainsString($testFile, $notReadableException->getMessage());
        self::assertStringContainsString('File exists but is not readable', $notReadableException->getMessage());

        $loadException = new ConfigurationLoadException('Custom error message', $testFile);
        self::assertStringContainsString('Configuration file error', $loadException->getMessage());
        self::assertStringContainsString($testFile, $loadException->getMessage());
        self::assertStringContainsString('Custom error message', $loadException->getMessage());
    }
}
