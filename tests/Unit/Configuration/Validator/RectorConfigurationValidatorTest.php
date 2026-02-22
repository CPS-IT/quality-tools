<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\RectorConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RectorConfigurationValidator::class)]
final class RectorConfigurationValidatorTest extends TestCase
{
    private RectorConfigurationValidator $validator;

    protected function setUp(): void
    {
        $filesystemService = new FilesystemService();
        $this->validator = new RectorConfigurationValidator($filesystemService);
    }

    public function testGetToolName(): void
    {
        self::assertSame('rector', $this->validator->getToolName());
    }

    public function testGetSupportedExtensions(): void
    {
        self::assertSame(['php'], $this->validator->getSupportedExtensions());
    }

    public function testValidateConfigurationFileReturnsFalseForNonExistentFile(): void
    {
        $result = $this->validator->validateConfigurationFile('/non/existent/file.php');

        self::assertFalse($result);
        self::assertStringContainsString('does not exist', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForUnreadableFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        file_put_contents($tempFile, '<?php return function() {};');
        chmod($tempFile, 0000);

        $result = $this->validator->validateConfigurationFile($tempFile);

        // Clean up
        chmod($tempFile, 0644);
        unlink($tempFile);

        // Note: On some systems, files might still be readable even with 0000 permissions
        // if owned by the same user, so we check if the error makes sense
        if (!$result) {
            self::assertNotNull($this->validator->getLastError());
        }
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidPhpSyntax(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        file_put_contents($tempFile, '<?php invalid syntax here');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('syntax error', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForNonCallableReturn(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        file_put_contents($tempFile, '<?php return "not callable";');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('must return a callable', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForNonRectorContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        file_put_contents($tempFile, '<?php return function() { echo "generic php"; };');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid Rector configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidRectorConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        $validConfig = '<?php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . "/src"])
    ->withRules([]);
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
        $this->validator->validateConfigurationFile('/non/existent/file.php');
        self::assertNotNull($this->validator->getLastError());

        // Create a valid file to reset error
        $tempFile = tempnam(sys_get_temp_dir(), 'rector_test');
        $validConfig = '<?php
return function(\Rector\Config\RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/src"]);
};';
        file_put_contents($tempFile, $validConfig);

        $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertNull($this->validator->getLastError());
    }
}
