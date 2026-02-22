<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\PhpCsFixerConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpCsFixerConfigurationValidator::class)]
final class PhpCsFixerConfigurationValidatorTest extends TestCase
{
    private PhpCsFixerConfigurationValidator $validator;

    protected function setUp(): void
    {
        $filesystemService = new FilesystemService();
        $this->validator = new PhpCsFixerConfigurationValidator($filesystemService);
    }

    public function testGetToolName(): void
    {
        self::assertSame('php-cs-fixer', $this->validator->getToolName());
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
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        file_put_contents($tempFile, '<?php invalid syntax here');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('syntax error', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForNonPhpCsFixerContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        file_put_contents($tempFile, '<?php return ["random" => "config"];');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid PHP-CS-Fixer configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidPhpCsFixerConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        $validConfig = '<?php
// PHP-CS-Fixer config with PhpCsFixer content
return [
    "PhpCsFixer" => true,
    "setRules" => ["@PSR12" => true],
];';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForConfigCreatePattern(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        $validConfig = '<?php
// Config::create() pattern in comments/strings but return array
$comment = "This uses Config::create() method";
return ["php-cs-fixer" => true, "setRules" => ["@PSR12" => true]];';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForArrayReturn(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        $validConfig = '<?php
// PHP-CS-Fixer array config
return [
    "php-cs-fixer" => true,
    "setRules" => ["@PSR12" => true],
];';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForCallableReturn(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        $validConfig = '<?php
return function() {
    // PhpCsFixer callable config
    return ["PhpCsFixer" => true];
};';
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
        $tempFile = tempnam(sys_get_temp_dir(), 'phpcs_test');
        $validConfig = '<?php
return ["php-cs-fixer" => true];';
        file_put_contents($tempFile, $validConfig);

        $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertNull($this->validator->getLastError());
    }
}
