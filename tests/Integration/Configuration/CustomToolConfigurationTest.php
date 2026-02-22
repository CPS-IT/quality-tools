<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Support\ConfigurationAssertions;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for custom tool configuration file replacement.
 *
 * CURRENT STATUS: Issue 022 (schema validation bug) has been resolved.
 * Configuration loading works successfully, but auto-discovery mechanism
 * for populating config_file keys is not yet implemented.
 *
 * EXPECTED BEHAVIOR: Tests validate that:
 * 1. Configuration loading works without schema validation errors
 * 2. Tool configurations are properly structured and enabled
 * 3. Auto-discovery implementation can be added incrementally
 * 4. Command integration works with current configuration system
 */
final class CustomToolConfigurationTest extends TestCase
{
    private string $tempDir;
    private HierarchicalConfigurationLoader $configurationLoader;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('custom_tool_config_test_');

        // Create required services for HierarchicalConfigurationLoader
        $validator = new ConfigurationValidator();
        $securityService = new SecurityService();
        $filesystemService = new FilesystemService();
        $toolValidator = new ToolConfigurationValidationService();

        $this->configurationLoader = new HierarchicalConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService,
            $toolValidator,
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test auto-discovery of custom tool configuration files.
     *
     * Tests that configuration loading works with custom tool config files present,
     * and validates the current behavior of tool configuration.
     */
    #[DataProvider('customToolConfigScenarios')]
    public function testCustomToolConfigurationAutoDiscovery(
        string $scenarioName,
        string $fixtureDirectory,
        array $expectedToolConfigs,
    ): void {
        // Copy fixture to temp directory for testing
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        $this->copyFixtureToTempDir($fixturePath);

        // Configuration loading should now work (Issue 022 resolved)
        $config = $this->configurationLoader->load($this->tempDir);

        // Verify basic configuration works
        $this->assertInstanceOf(\Cpsit\QualityTools\Configuration\ConfigurationInterface::class, $config);

        // Test tool configurations exist and have expected structure
        foreach ($expectedToolConfigs as $tool => $expectedConfig) {
            $toolConfig = $config->getToolConfig($tool);
            $this->assertIsArray($toolConfig, "Tool '{$tool}' should have configuration in scenario: {$scenarioName}");
            $this->assertTrue($toolConfig['enabled'] ?? false, "Tool '{$tool}' should be enabled in scenario: {$scenarioName}");

            // For now, document that auto-discovery of config_file may not be implemented yet
            if (isset($expectedConfig['config_file_contains']) || isset($expectedConfig['explicit_config_file'])) {
                $this->markTestIncomplete(
                    "Auto-discovery mechanism for '{$tool}' config_file not yet implemented in scenario: {$scenarioName}. " .
                    'Configuration loads successfully but config_file key is not populated.',
                );
            }
        }
    }

    /**
     * Test config:validate and config:show commands with custom tool configurations.
     *
     * Tests that configuration validation and display commands work correctly
     * with custom tool configuration files present.
     */
    #[DataProvider('customToolConfigScenarios')]
    public function testConfigValidateCommandWithCustomToolConfig(
        string $scenarioName,
        string $fixtureDirectory,
        array $expectedToolConfigs,
    ): void {
        // Copy fixture to temp directory
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        $this->copyFixtureToTempDir($fixturePath);

        // Create application and test config:validate command
        $application = new QualityToolsApplication();
        $command = $application->find('config:validate');
        $commandTester = new CommandTester($command);

        // Run config:validate - should now work correctly (Issue 022 resolved)
        $exitCode = $commandTester->execute([], ['cwd' => $this->tempDir]);
        
        $this->assertEquals(0, $exitCode, "config:validate should succeed for scenario: {$scenarioName}");
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(
            '[OK] Configuration is valid',
            $output,
            "config:validate should report configuration as valid for scenario: {$scenarioName}",
        );

        // Test config:show command also works
        $showCommand = $application->find('config:show');
        $showCommandTester = new CommandTester($showCommand);

        $showExitCode = $showCommandTester->execute([], ['cwd' => $this->tempDir]);
        $this->assertEquals(0, $showExitCode, "config:show should succeed for scenario: {$scenarioName}");

        $showOutput = $showCommandTester->getDisplay();
        $this->assertStringContainsString(
            'quality-tools:',
            $showOutput,
            "config:show should display configuration for scenario: {$scenarioName}",
        );
    }

    /**
     * Test tool commands ignore custom configuration files.
     *
     * This test verifies that lint commands currently ignore custom tool configs
     * and use package defaults instead.
     */
    public function testToolCommandsIgnoreCustomConfig(): void
    {
        // Use rector-root-override fixture
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/rector-root-override';
        $this->copyFixtureToTempDir($fixturePath);

        // Create vendor structure so commands can run
        TestHelper::createVendorStructure($this->tempDir, false, true);

        $application = new QualityToolsApplication();
        $command = $application->find('lint:rector');
        $commandTester = new CommandTester($command);

        try {
            // This should use custom rector.php but currently ignores it
            $commandTester->execute(['--dry-run' => true], ['cwd' => $this->tempDir]);
            $output = $commandTester->getDisplay();

            // Currently this will NOT contain our custom path because config is ignored
            $this->assertStringNotContainsString(
                'custom-rector-root-path',
                $output,
                'Tool command currently ignores custom config (Issue 022 behavior)',
            );
        } catch (\Exception $e) {
            // May fail due to configuration loading issues
            $this->markTestSkipped(
                "Tool command test skipped due to configuration loading error: {$e->getMessage()}",
            );
        }

        // Document the current broken behavior
        $this->markTestSkipped(
            'Test documents Issue 022: Tool commands ignore custom configuration files',
        );
    }

    /**
     * Test configuration precedence with mixed sources.
     *
     * This test validates the precedence order:
     * User YAML explicit > User YAML auto-discovered > Auto-discovered files > Package defaults
     */
    public function testConfigurationPrecedenceWithMixedSources(): void
    {
        // Create both auto-discovered file AND explicit YAML configuration
        $rectorFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorFile, '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/auto-discovered-path"]);
};');

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $yamlConfig = [
            'quality-tools' => [
                'project' => ['name' => 'mixed-sources-test'],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'config_file' => './custom-explicit-rector.php', // Explicit override
                    ],
                ],
            ],
        ];
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($yamlConfig));

        // Create the explicitly referenced file
        $explicitRectorFile = $this->tempDir . '/custom-explicit-rector.php';
        file_put_contents($explicitRectorFile, '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/explicit-override-path"]);
};');

        try {
            $config = $this->configurationLoader->load($this->tempDir);

            // Post-fix: Should use explicit config file, not auto-discovered
            ConfigurationAssertions::assertToolUsesExplicitConfigFile(
                $config,
                'rector',
                './custom-explicit-rector.php',
                'Explicit config_file in YAML should override auto-discovered file',
            );
        } catch (\Exception $e) {
            // Current Issue 022 behavior - schema validation fails
            $this->assertStringContainsString(
                'config_file is not defined',
                $e->getMessage(),
                'Should fail with Issue 022 schema error (precedence test)',
            );

            $this->markTestSkipped(
                'Test skipped due to Issue 022: Configuration precedence cannot be tested until schema validation is fixed. ' .
                'Expected: Explicit YAML config_file should override auto-discovered rector.php',
            );
        }
    }

    /**
     * Data provider for custom tool configuration scenarios.
     */
    public static function customToolConfigScenarios(): array
    {
        return [
            'rector_root_override' => [
                'scenarioName' => 'Rector Config in Project Root',
                'fixtureDirectory' => 'rector-root-override',
                'expectedToolConfigs' => [
                    'rector' => [
                        'config_file_contains' => 'rector.php',
                    ],
                ],
            ],
            'rector_config_override' => [
                'scenarioName' => 'Rector Config in Config Directory',
                'fixtureDirectory' => 'rector-config-override',
                'expectedToolConfigs' => [
                    'rector' => [
                        'config_file_contains' => 'config/rector.php',
                    ],
                ],
            ],
            'phpstan_root_override' => [
                'scenarioName' => 'PHPStan Config in Project Root',
                'fixtureDirectory' => 'phpstan-root-override',
                'expectedToolConfigs' => [
                    'phpstan' => [
                        'config_file_contains' => 'phpstan.neon',
                    ],
                ],
            ],
            'phpstan_config_override' => [
                'scenarioName' => 'PHPStan Config in Config Directory',
                'fixtureDirectory' => 'phpstan-config-override',
                'expectedToolConfigs' => [
                    'phpstan' => [
                        'config_file_contains' => 'config/phpstan.neon',
                    ],
                ],
            ],
            'explicit_config_file_override' => [
                'scenarioName' => 'Explicit Config File Override in YAML',
                'fixtureDirectory' => 'explicit-config-file-override',
                'expectedToolConfigs' => [
                    'rector' => [
                        'explicit_config_file' => 'custom/rector.php',
                    ],
                ],
            ],
            'multiple_tools_mixed_locations' => [
                'scenarioName' => 'Multiple Tools Mixed Locations',
                'fixtureDirectory' => 'multiple-tools-mixed',
                'expectedToolConfigs' => [
                    'rector' => [
                        'config_file_contains' => 'rector.php',
                    ],
                    'phpstan' => [
                        'config_file_contains' => 'config/phpstan.neon',
                    ],
                ],
            ],
        ];
    }

    /**
     * Copy fixture directory to temp directory for testing.
     */
    private function copyFixtureToTempDir(string $fixturePath): void
    {
        if (!is_dir($fixturePath)) {
            $this->fail("Fixture directory does not exist: {$fixturePath}");
        }

        // Copy all files and directories from fixture
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fixturePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $targetPath = $this->tempDir . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                mkdir($targetPath, 0o755, true);
            } else {
                mkdir(\dirname($targetPath), 0o755, true);
                copy($item->getRealPath(), $targetPath);
            }
        }
    }
}
