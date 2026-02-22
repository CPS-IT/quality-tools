<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration\Validator;

use Cpsit\QualityTools\Configuration\Validator\ToolConfigurationValidatorInterface;
use Cpsit\QualityTools\Configuration\Validator\ToolValidationTrait;
use Cpsit\QualityTools\Service\FilesystemService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ToolValidationTrait::class)]
final class ToolValidationTraitTest extends TestCase
{
    public function testGetToolNameThrowsExceptionWhenConstantMissing(): void
    {
        $validator = new class (new FilesystemService()) implements ToolConfigurationValidatorInterface {
            use ToolValidationTrait;

            public function validateConfigurationFile(string $path): bool
            {
                return true;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must define TOOL_NAME constant');

        $validator->getToolName();
    }

    public function testGetSupportedExtensionsThrowsExceptionWhenConstantMissing(): void
    {
        $validator = new class (new FilesystemService()) implements ToolConfigurationValidatorInterface {
            use ToolValidationTrait;

            public function validateConfigurationFile(string $path): bool
            {
                return true;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must define SUPPORTED_EXTENSIONS constant');

        $validator->getSupportedExtensions();
    }

    public function testGetToolNameReturnsConstantValue(): void
    {
        $validator = new class (new FilesystemService()) implements ToolConfigurationValidatorInterface {
            use ToolValidationTrait;

            public const string TOOL_NAME = 'test-tool';
            public const array SUPPORTED_EXTENSIONS = ['php'];

            public function validateConfigurationFile(string $path): bool
            {
                return true;
            }
        };

        self::assertSame('test-tool', $validator->getToolName());
    }

    public function testGetSupportedExtensionsReturnsConstantValue(): void
    {
        $validator = new class (new FilesystemService()) implements ToolConfigurationValidatorInterface {
            use ToolValidationTrait;

            public const string TOOL_NAME = 'test-tool';
            public const array SUPPORTED_EXTENSIONS = ['php', 'yaml'];

            public function validateConfigurationFile(string $path): bool
            {
                return true;
            }
        };

        self::assertSame(['php', 'yaml'], $validator->getSupportedExtensions());
    }
}
