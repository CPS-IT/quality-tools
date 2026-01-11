<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Exception\ConfigurationFileNotFoundException;
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationFileNotFoundException::class)]
#[CoversClass(ConfigurationFileNotReadableException::class)]
#[CoversClass(ConfigurationLoadException::class)]
final class ConfigurationFileExceptionTest extends TestCase
{
    private SimpleConfigurationLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_exception_test_');
        $this->loader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    #[Test]
    public function defaultConfigurationUsedIfFileNotFound(): void
    {
        // Create a directory with no config files
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir);

        // When trying to load from a directory with no config, it should still work
        // (uses defaults), but if we specifically try to load a non-existent file
        // through internal methods, we should get the specific exception

        $config = $this->loader->load($emptyDir);

        self::assertSame('8.3', $config->getProjectPhpVersion()); // default PHP version
    }

    public function testFileValidationPreventsPHPWarnings(): void
    {
        // This test demonstrates that our new approach prevents PHP warnings
        // by checking file existence and readability before calling file_get_contents

        // Create an unreadable config file using the standard config filename
        $unreadableFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($unreadableFile, 'quality-tools: {}');
        chmod($unreadableFile, 0o000);

        try {
            // The old approach would call file_get_contents() directly and generate warnings
            // Our new approach checks is_readable() first and throws specific exceptions

            $this->expectException(ConfigurationFileNotReadableException::class);
            $this->expectExceptionMessage('File exists but is not readable');

            // This will trigger the unreadable file exception since we have a config file present
            // but it's unreadable
            $this->loader->load($this->tempDir);
        } finally {
            // Restore permissions for cleanup
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
