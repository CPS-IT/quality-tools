<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Configuration\Validator\ToolConfigurationValidatorInterface;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ToolConfigurationValidationService::class)]
final class ToolConfigurationValidationServiceTest extends TestCase
{
    private ToolConfigurationValidationService $service;

    /**
     * @var MockObject&ToolConfigurationValidatorInterface
     */
    private MockObject $rectorValidator;

    /**
     * @var MockObject&ToolConfigurationValidatorInterface
     */
    private MockObject $phpstanValidator;

    protected function setUp(): void
    {
        $this->rectorValidator = $this->createMock(ToolConfigurationValidatorInterface::class);
        $this->phpstanValidator = $this->createMock(ToolConfigurationValidatorInterface::class);

        $this->rectorValidator
            ->method('getToolName')
            ->willReturn('rector');

        $this->phpstanValidator
            ->method('getToolName')
            ->willReturn('phpstan');

        $this->service = new ToolConfigurationValidationService([
            $this->rectorValidator,
            $this->phpstanValidator,
        ]);
    }

    public function testConstructorRegistersValidators(): void
    {
        self::assertTrue($this->service->hasValidator('rector'));
        self::assertTrue($this->service->hasValidator('phpstan'));
        self::assertFalse($this->service->hasValidator('nonexistent'));
    }

    public function testRegisterValidator(): void
    {
        $newValidator = $this->createMock(ToolConfigurationValidatorInterface::class);
        $newValidator->method('getToolName')->willReturn('new-tool');

        $this->service->registerValidator($newValidator);

        self::assertTrue($this->service->hasValidator('new-tool'));
    }

    public function testValidateConfigurationFileReturnsFalseForUnregisteredTool(): void
    {
        $result = $this->service->validateConfigurationFile('unknown-tool', '/path/to/config.php');

        self::assertFalse($result);
    }

    public function testValidateConfigurationFileCallsValidatorAndReturnsResult(): void
    {
        $configPath = '/path/to/rector.php';

        $this->rectorValidator
            ->expects(self::once())
            ->method('validateConfigurationFile')
            ->with($configPath)
            ->willReturn(true);

        $result = $this->service->validateConfigurationFile('rector', $configPath);

        self::assertTrue($result);
    }

    public function testGetLastErrorReturnsValidatorError(): void
    {
        $errorMessage = 'Validation failed';

        $this->rectorValidator
            ->method('getLastError')
            ->willReturn($errorMessage);

        $result = $this->service->getLastError('rector');

        self::assertSame($errorMessage, $result);
    }

    public function testGetLastErrorReturnsMessageForUnregisteredTool(): void
    {
        $result = $this->service->getLastError('unknown-tool');

        self::assertSame('No validator registered for tool: unknown-tool', $result);
    }

    public function testGetRegisteredTools(): void
    {
        $tools = $this->service->getRegisteredTools();

        self::assertContains('rector', $tools);
        self::assertContains('phpstan', $tools);
        self::assertCount(2, $tools);
    }

    public function testGetSupportedExtensions(): void
    {
        $extensions = ['php'];

        $this->rectorValidator
            ->method('getSupportedExtensions')
            ->willReturn($extensions);

        $result = $this->service->getSupportedExtensions('rector');

        self::assertSame($extensions, $result);
    }

    public function testGetSupportedExtensionsReturnsEmptyArrayForUnregisteredTool(): void
    {
        $result = $this->service->getSupportedExtensions('unknown-tool');

        self::assertSame([], $result);
    }

    public function testValidateMultipleConfigurations(): void
    {
        $configPaths = [
            'rector' => '/path/to/rector.php',
            'phpstan' => '/path/to/phpstan.neon',
        ];

        $this->rectorValidator
            ->expects(self::once())
            ->method('validateConfigurationFile')
            ->with('/path/to/rector.php')
            ->willReturn(true);

        $this->phpstanValidator
            ->expects(self::once())
            ->method('validateConfigurationFile')
            ->with('/path/to/phpstan.neon')
            ->willReturn(false);

        $this->phpstanValidator
            ->method('getLastError')
            ->willReturn('PHPStan validation failed');

        $results = $this->service->validateMultipleConfigurations($configPaths);

        $expected = [
            'rector' => [
                'valid' => true,
                'error' => null,
            ],
            'phpstan' => [
                'valid' => false,
                'error' => 'PHPStan validation failed',
            ],
        ];

        self::assertSame($expected, $results);
    }

    public function testValidateMultipleConfigurationsHandlesUnregisteredTool(): void
    {
        $configPaths = [
            'unknown-tool' => '/path/to/unknown.conf',
        ];

        $results = $this->service->validateMultipleConfigurations($configPaths);

        $expected = [
            'unknown-tool' => [
                'valid' => false,
                'error' => 'No validator registered for tool: unknown-tool',
            ],
        ];

        self::assertSame($expected, $results);
    }

    public function testConstructorWithEmptyValidators(): void
    {
        $emptyService = new ToolConfigurationValidationService();

        self::assertSame([], $emptyService->getRegisteredTools());
        self::assertFalse($emptyService->hasValidator('rector'));
    }

    public function testValidatorOverride(): void
    {
        $newRectorValidator = $this->createMock(ToolConfigurationValidatorInterface::class);
        $newRectorValidator->method('getToolName')->willReturn('rector');
        $newRectorValidator->method('getSupportedExtensions')->willReturn(['php', 'inc']);

        $this->service->registerValidator($newRectorValidator);

        // Should still only have one rector validator (the new one)
        $tools = $this->service->getRegisteredTools();
        self::assertContains('rector', $tools);

        // Should use the new validator's extensions
        $extensions = $this->service->getSupportedExtensions('rector');
        self::assertSame(['php', 'inc'], $extensions);
    }
}
