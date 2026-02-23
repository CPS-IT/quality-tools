<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\PhpstanConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(PhpstanConfigurationValidator::class)]
final class PhpstanConfigurationValidatorTest extends TestCase
{
    private PhpstanConfigurationValidator $validator;

    protected function setUp(): void
    {
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $this->validator = new PhpstanConfigurationValidator($filesystemService);
    }

    public function testGetToolName(): void
    {
        self::assertSame('phpstan', $this->validator->getToolName());
    }

    public function testGetSupportedExtensions(): void
    {
        self::assertSame(['neon', 'neon.dist', 'php', 'yml', 'yaml'], $this->validator->getSupportedExtensions());
    }

    public function testValidateConfigurationFileReturnsFalseForNonExistentFile(): void
    {
        $result = $this->validator->validateConfigurationFile('/non/existent/file.neon');

        self::assertFalse($result);
        self::assertStringContainsString('does not exist', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidNeonConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.neon';
        $validConfig = 'parameters:
    level: 6
    paths:
        - src
    phpstan:
        includes:
            - extension.neon
';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidNeonContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.neon';
        $invalidConfig = 'random:
    content: without any tool specifics
    other: data
';
        file_put_contents($tempFile, $invalidConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid PHPStan NEON configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidPhpConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.php';
        $validConfig = '<?php
return [
    "level" => 6,
    "paths" => ["src"],
    "phpstan" => true,
];';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidPhpSyntax(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.php';
        file_put_contents($tempFile, '<?php invalid syntax here');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('syntax error', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidPhpContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.php';
        $invalidConfig = '<?php
return [
    "random" => "content without any tool specifics",
    "other" => "data",
];';
        file_put_contents($tempFile, $invalidConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid PHPStan PHP configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidYamlConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.yml';
        $validConfig = 'phpstan:
  level: 6
  paths:
    - src
';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testGetLastErrorReturnsNullInitially(): void
    {
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileResetsLastError(): void
    {
        // Force an error first
        $this->validator->validateConfigurationFile('/non/existent/file.neon');
        self::assertNotNull($this->validator->getLastError());

        // Create a valid file to reset error
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test') . '.neon';
        $validConfig = 'parameters:
    level: 6
';
        file_put_contents($tempFile, $validConfig);

        $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertNull($this->validator->getLastError());
    }
}
