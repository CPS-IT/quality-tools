<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\FractorConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(FractorConfigurationValidator::class)]
final class FractorConfigurationValidatorTest extends TestCase
{
    private FractorConfigurationValidator $validator;

    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $this->validator = new FractorConfigurationValidator($filesystemService);
    }

    public function testGetToolName(): void
    {
        self::assertSame('fractor', $this->validator->getToolName());
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

    public function testValidateConfigurationFileReturnsFalseForInvalidPhpSyntax(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        file_put_contents($tempFile, '<?php invalid syntax here');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('syntax error', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForNonFractorContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        file_put_contents($tempFile, '<?php return ["random" => "config without tool specifics"];');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid Fractor configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidReturnType(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        file_put_contents($tempFile, '<?php
        // Has fractor content but wrong return type
        use Fractor\Config\FractorConfig;
        return "string instead of callable or array";
        ');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('must return a callable or configuration array', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidFractorConfigWithCallable(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        $validConfig = '<?php
use Fractor\Config\FractorConfig;

return function (FractorConfig $fractorConfig): void {
    $fractorConfig->paths([
        __DIR__ . "/config/sites/*/setup.typoscript",
    ]);
    $fractorConfig->rules([]);
};';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidFractorConfigWithArray(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        $validConfig = '<?php
// Fractor configuration as array
return [
    "paths" => ["config/sites/*/setup.typoscript"],
    "fractor" => true,
];';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForTypoScriptRelatedContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        $validConfig = '<?php
// TypoScript processing config
return [
    "typoscript" => ["setup.ts", "constants.ts"],
    "TypoScript" => true,
];';
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
        $tempFile = tempnam(sys_get_temp_dir(), 'fractor_test');
        $validConfig = '<?php
return function() {
    // Simple fractor config
    return ["fractor" => true];
};';
        file_put_contents($tempFile, $validConfig);

        $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertNull($this->validator->getLastError());
    }
}
