<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\Validator\TyposcriptLintConfigurationValidator;
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
        $toolValidator = new ToolConfigurationValidationService([
            new TyposcriptLintConfigurationValidator($filesystemService),
        ]);

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
        $this->assertTrue(
            copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml'),
            'fixture copy failed',
        );

        $resolved = $this->configLoader->resolveToolConfigPath($this->tempDir, 'typoscript-lint');

        $this->assertNotNull($resolved, 'typoscript-lint.yaml in project root should be discovered');
        $this->assertStringEndsWith('typoscript-lint.yaml', $resolved);
    }

    public function testLoadingConfigWithTyposcriptLintYamlDoesNotThrow(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        $this->assertTrue(
            copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml'),
            'fixture copy failed',
        );

        $config = $this->configLoader->load($this->tempDir);

        $this->assertIsArray($config->toArray());

        $errors = $this->configLoader->getConfigurationErrors($this->tempDir);
        $this->assertEmpty(
            $errors,
            'typoscript-lint.yaml must not produce quality-tools schema validation errors: ' . implode(', ', $errors),
        );
    }

    public function testConfigValidateProducesNoFalsePositivesWithTyposcriptLintYaml(): void
    {
        file_put_contents(
            $this->tempDir . '/.quality-tools.yaml',
            "quality-tools:\n  tools:\n    rector:\n      enabled: true\n",
        );

        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        $this->assertTrue(
            copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml'),
            'fixture copy failed',
        );

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                VendorDirectoryDetector::clearCache();
                ServiceContainer::reset();

                $application = new QualityToolsApplication();
                $command = $application->find('config:validate');
                $commandTester = new CommandTester($command);

                $exitCode = $commandTester->execute([]);
                $output = $commandTester->getDisplay();

                $this->assertEquals(
                    0,
                    $exitCode,
                    'config:validate must succeed with typoscript-lint.yaml in project root. Output: ' . $output,
                );

                $this->assertStringContainsString(
                    'Configuration is valid.',
                    $output,
                    'config:validate must report a valid configuration, not a false positive from typoscript-lint.yaml',
                );
            },
        );
    }

    public function testLintTyposcriptCommandSucceedsWithYamlConfig(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/typoscriptLintYamlOverride/typoscript-lint.yaml';
        $this->assertTrue(
            copy($fixturePath, $this->tempDir . '/typoscript-lint.yaml'),
            'fixture copy failed',
        );

        $vendorDir = TestHelper::createVendorStructure($this->tempDir, false, true);
        $binDir = $vendorDir . '/bin';

        $this->assertTrue(
            copy(
                __DIR__ . '/../../Fixtures/mockExecutables/typoscript-lint',
                $binDir . '/typoscript-lint',
            ),
            'mock executable copy failed',
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

                $this->assertMatchesRegularExpression(
                    '/Config: .*typoscript-lint\.yaml/',
                    $output,
                    'Command must pass the project-level typoscript-lint.yaml to the binary via -c',
                );
            },
        );
    }
}
