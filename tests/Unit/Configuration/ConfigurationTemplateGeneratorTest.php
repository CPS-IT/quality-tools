<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationTemplateGenerator;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationTemplateGenerator::class)]
final class ConfigurationTemplateGeneratorTest extends TestCase
{
    private ConfigurationTemplateGenerator $generator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->generator = new ConfigurationTemplateGenerator();
        $this->tempDir = TestHelper::createTempDirectory('template_gen_test_');
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testIsValidTemplateAcceptsKnownTemplates(): void
    {
        self::assertTrue($this->generator->isValidTemplate('default'));
        self::assertTrue($this->generator->isValidTemplate('typo3-extension'));
        self::assertTrue($this->generator->isValidTemplate('typo3-site-package'));
        self::assertTrue($this->generator->isValidTemplate('typo3-distribution'));
    }

    public function testIsValidTemplateRejectsUnknown(): void
    {
        self::assertFalse($this->generator->isValidTemplate('unknown'));
        self::assertFalse($this->generator->isValidTemplate(''));
    }

    public function testGetAvailableTemplatesReturnsFourEntries(): void
    {
        $templates = $this->generator->getAvailableTemplates();

        self::assertCount(4, $templates);
        self::assertArrayHasKey('default', $templates);
        self::assertArrayHasKey('typo3-extension', $templates);
        self::assertArrayHasKey('typo3-site-package', $templates);
        self::assertArrayHasKey('typo3-distribution', $templates);
    }

    #[DataProvider('templateDataProvider')]
    public function testGenerateProducesValidYaml(string $template, string $expectedSubstring): void
    {
        $content = $this->generator->generate($template, $this->tempDir);

        self::assertStringContainsString('quality-tools:', $content);
        self::assertStringContainsString($expectedSubstring, $content);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function templateDataProvider(): array
    {
        return [
            'default' => ['default', 'packages/'],
            'extension' => ['typo3-extension', 'Classes/'],
            'site-package' => ['typo3-site-package', 'packages/'],
            'distribution' => ['typo3-distribution', 'config/sites/'],
        ];
    }

    public function testGenerateDetectsProjectNameFromComposerJson(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode(['name' => 'vendor/my-project', 'type' => 'project']),
        );

        $content = $this->generator->generate('default', $this->tempDir);

        self::assertStringContainsString('vendor/my-project', $content);
    }

    public function testGenerateFallsBackToDirectoryName(): void
    {
        // No composer.json -- uses directory basename
        $content = $this->generator->generate('default', $this->tempDir);

        self::assertStringContainsString(basename($this->tempDir), $content);
    }

    public function testGenerateHandlesInvalidComposerJson(): void
    {
        file_put_contents($this->tempDir . '/composer.json', 'not valid json');

        $content = $this->generator->generate('default', $this->tempDir);

        // Falls back to directory name
        self::assertStringContainsString(basename($this->tempDir), $content);
    }

    public function testGenerateHandlesComposerJsonWithoutName(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode(['type' => 'project']),
        );

        $content = $this->generator->generate('default', $this->tempDir);

        self::assertStringContainsString(basename($this->tempDir), $content);
    }

    public function testUnknownTemplateFallsBackToDefault(): void
    {
        $defaultContent = $this->generator->generate('default', $this->tempDir);
        $unknownContent = $this->generator->generate('nonexistent', $this->tempDir);

        self::assertEquals($defaultContent, $unknownContent);
    }

    public function testExtensionTemplateHasHigherPhpstanLevel(): void
    {
        $content = $this->generator->generate('typo3-extension', $this->tempDir);

        self::assertStringContainsString('level: 8', $content);
        self::assertStringContainsString('memory_limit: "512M"', $content);
    }

    public function testDistributionTemplateHasHigherMemoryLimit(): void
    {
        $content = $this->generator->generate('typo3-distribution', $this->tempDir);

        self::assertStringContainsString('memory_limit: "2G"', $content);
        self::assertStringContainsString('max_processes: 8', $content);
    }
}
