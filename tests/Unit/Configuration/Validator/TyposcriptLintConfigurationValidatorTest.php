<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\TyposcriptLintConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TyposcriptLintConfigurationValidator::class)]
final class TyposcriptLintConfigurationValidatorTest extends TestCase
{
    private TyposcriptLintConfigurationValidator $validator;

    protected function setUp(): void
    {
        $filesystemService = new FilesystemService(
            new Filesystem(),
            new SecurityService(),
        );
        $this->validator = new TyposcriptLintConfigurationValidator($filesystemService);
    }

    public function testGetToolName(): void
    {
        self::assertSame('typoscript-lint', $this->validator->getToolName());
    }

    public function testGetSupportedExtensions(): void
    {
        self::assertSame(['yml', 'yaml'], $this->validator->getSupportedExtensions());
    }

    public function testValidateConfigurationFileReturnsFalseForNonExistentFile(): void
    {
        $result = $this->validator->validateConfigurationFile('/non/existent/file.yml');

        self::assertFalse($result);
        self::assertStringContainsString('does not exist', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidYamlConfigWithSniffs(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        $validConfig = 'sniffs:
  - TypoScriptSniff
paths:
  - "config/sites/*/setup.typoscript"
fileExtensions:
  - ts
  - typoscript
';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForValidYamlConfigWithPaths(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        $validConfig = 'paths:
  - "config/sites/"
  - "packages/"
excludePatterns:
  - "*.backup"
';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForNestedTyposcriptLintStructure(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        $validConfig = 'typoscript-lint:
  sniffs:
    - TypoScriptSniff
  paths:
    - "config/"
';
        file_put_contents($tempFile, $validConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidYamlContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        $invalidConfig = 'random:
  content: without any tool specifics
  other: unrelated data
';
        file_put_contents($tempFile, $invalidConfig);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid TypoScript-Lint configuration', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForInvalidYamlSyntax(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        file_put_contents($tempFile, 'invalid: yaml: syntax: [unclosed');

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('YAML validation failed', $this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsTrueForGenericFormatWithTyposcriptContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.txt';
        $validContent = 'This is a TypoScript configuration file
with typoscript content and paths defined.
fileExtensions are also specified here.
';
        file_put_contents($tempFile, $validContent);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertTrue($result);
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileReturnsFalseForGenericFormatWithoutTyposcriptContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.txt';
        $invalidContent = 'This is a random configuration file
without any relevant content.
';
        file_put_contents($tempFile, $invalidContent);

        $result = $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertFalse($result);
        self::assertStringContainsString('does not appear to be a valid TypoScript-Lint configuration', $this->validator->getLastError());
    }

    public function testGetLastErrorReturnsNullInitially(): void
    {
        self::assertNull($this->validator->getLastError());
    }

    public function testValidateConfigurationFileResetsLastError(): void
    {
        // Force an error first
        $this->validator->validateConfigurationFile('/non/existent/file.yml');
        self::assertNotNull($this->validator->getLastError());

        // Create a valid file to reset error
        $tempFile = tempnam(sys_get_temp_dir(), 'tslint_test') . '.yml';
        $validConfig = 'sniffs:
  - TypoScriptSniff
';
        file_put_contents($tempFile, $validConfig);

        $this->validator->validateConfigurationFile($tempFile);

        unlink($tempFile);

        self::assertNull($this->validator->getLastError());
    }
}
