<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\DependencyInjection\ServiceContainer;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration tests for GL#7: typoscript-lint.yaml config overwrite support.
 *
 * Verifies that a typoscript-lint.yaml file in the project root is recognized
 * as a tool config and does not cause quality-tools schema validation errors.
 */
#[CoversNothing]
final class TyposcriptLintYamlConfigTest extends TestCase
{
    private string $tempDir;
    private ConfigurationLoader $configLoader;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('typoscript_lint_yaml_test_');

        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService(new Filesystem(), $securityService);
        $toolValidator = new ToolConfigurationValidationService();

        $this->configLoader = new ConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService,
            $toolValidator,
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        VendorDirectoryDetector::clearCache();
        ServiceContainer::reset();
    }

    public function testTyposcriptLintYamlIsDiscoveredAsToolConfig(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml');

        $resolved = $this->configLoader->resolveToolConfigPath($this->tempDir, 'typoscript-lint');

        $this->assertNotNull($resolved, 'typoscript-lint.yaml in project root should be discovered');
        $this->assertStringEndsWith('typoscript-lint.yaml', $resolved);
    }

    public function testLoadingConfigWithTyposcriptLintYamlDoesNotThrow(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml');

        // Must not throw a schema validation exception; assert the config loaded successfully
        $config = $this->configLoader->load($this->tempDir);

        $this->assertIsArray($config->toArray());
    }

    public function testLintTyposcriptCommandSucceedsWithYamlConfig(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml');

        $vendorDir = TestHelper::createVendorStructure($this->tempDir, false, true);
        $binDir = $vendorDir . '/bin';

        copy(
            __DIR__ . '/../../Fixtures/mockExecutables/typoscript-lint',
            $binDir . '/typoscript-lint',
        );
        chmod($binDir . '/typoscript-lint', 0o755);

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                VendorDirectoryDetector::clearCache();
                ServiceContainer::reset();

                $application = new QualityToolsApplication();
                $command = $application->find('lint:typoscript');
                $commandTester = new CommandTester($command);

                $exitCode = $commandTester->execute([]);
                $output = $commandTester->getDisplay();

                $this->assertEquals(
                    0,
                    $exitCode,
                    'lint:typoscript should succeed when typoscript-lint.yaml is in project root. Output: ' . $output,
                );

                $this->assertStringContainsString(
                    'typoscript-lint.yaml',
                    $output,
                    'Command should use the project-level typoscript-lint.yaml config',
                );
            },
        );
    }
}
