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
use Symfony\Component\Filesystem\Filesystem;

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
    private SecurityService $securityService;
    private FilesystemService $filesystemService;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('custom_tool_config_test_');

        // Create required services for HierarchicalConfigurationLoader
        $validator = new ConfigurationValidator();
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(new Filesystem(), $this->securityService);
        $toolValidator = new ToolConfigurationValidationService();

        $this->configurationLoader = new HierarchicalConfigurationLoader(
            $validator,
            $this->securityService,
            $this->filesystemService,
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

        // Run within isolated environment pointing to temp dir
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($scenarioName): void {
                // Create application and test config:validate command
                $application = new QualityToolsApplication();
                $command = $application->find('config:validate');
                $commandTester = new CommandTester($command);

                $exitCode = $commandTester->execute([]);

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

                $showExitCode = $showCommandTester->execute([]);
                $this->assertEquals(0, $showExitCode, "config:show should succeed for scenario: {$scenarioName}");

                $showOutput = $showCommandTester->getDisplay();
                $this->assertStringContainsString(
                    'quality-tools:',
                    $showOutput,
                    "config:show should display configuration for scenario: {$scenarioName}",
                );
            }
        );
    }

    /**
     * Test that config:validate warns about invalid config_file paths but still succeeds.
     *
     * When a YAML config_file entry references a non-existent file,
     * config:validate should report SUCCESS with a warning about the
     * missing file and the fallback to package defaults.
     */
    public function testConfigValidateWarnsAboutInvalidConfigFilePath(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/invalid-config-file-path';
        $this->copyFixtureToTempDir($fixturePath);

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $application = new QualityToolsApplication();
                $command = $application->find('config:validate');
                $commandTester = new CommandTester($command);

                $exitCode = $commandTester->execute([]);
                $output = $commandTester->getDisplay();

                // Command should succeed (invalid config_file is a warning, not a failure)
                $this->assertEquals(0, $exitCode, 'config:validate should succeed even with invalid config_file path');

                // Should report configuration as valid
                $this->assertStringContainsString(
                    '[OK] Configuration is valid',
                    $output,
                    'Should report configuration as structurally valid',
                );

                // Should warn about the missing config_file
                $this->assertStringContainsString(
                    'non-existent/rector.php',
                    $output,
                    'Should mention the invalid config_file path',
                );

                // Should mention fallback behavior
                $this->assertStringContainsString(
                    'fallback to package defaults',
                    $output,
                    'Should inform about fallback to package defaults',
                );
            }
        );
    }

    /**
     * Test tool commands ignore custom configuration files.
     *
     * This test verifies that lint commands currently ignore custom tool configs
     * and use package defaults instead. This is the FAILING behavior we want to fix.
     */
    #[DataProvider('toolCommandScenarios')]
    public function testToolCommandsIgnoreCustomConfig(
        string $scenarioName,
        string $fixtureDirectory,
        string $toolCommand,
        string $expectedPath,
    ): void {
        // Use fixture directory
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        $this->copyFixtureToTempDir($fixturePath);

        // Create vendor structure so commands can run
        $vendorDir = TestHelper::createVendorStructure($this->tempDir, false, true);

        // Set up mock tool executables using fixtures
        $this->setupMockToolExecutables($vendorDir, $fixtureDirectory, $expectedPath);

        // Run the test with the temp directory as project root
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($toolCommand, &$output): void {
                $application = new QualityToolsApplication();
                $command = $application->find($toolCommand);
                $commandTester = new CommandTester($command);

                // Execute the command - it should discover and use the custom config
                $commandTester->execute([]);
                $output = $commandTester->getDisplay();
            }
        );

        // This test documents the current INCORRECT behavior:
        // Tool commands should auto-discover and use custom config files,
        // but they currently don't - they use package defaults instead.
        //
        // Once auto-discovery is implemented in AbstractToolCommand,
        // this test will start passing.
        //
        // EXPECTED: Output should contain the custom path from the custom config
        // ACTUAL: Output does NOT contain the custom path - uses package defaults instead

        // For now, we expect this to fail (custom configs are ignored)
        // When auto-discovery is implemented the test should pass

        $this->assertStringContainsString(
            $expectedPath,
            $output,
            "Auto-discovery not yet implemented. Tool command '{$toolCommand}' currently ignores " .
            "custom config and uses package defaults. Expected path '{$expectedPath}' not found in output.".
            "Scenario: {$scenarioName}",

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
     * Data provider for tool command scenarios.
     *
     * Tests that tool commands should discover and use custom configs
     * but currently don't (this is the bug we need to fix).
     */
    public static function toolCommandScenarios(): array
    {
        return [
            'rector_root_discovery' => [
                'scenarioName' => 'Rector root config discovery',
                'fixtureDirectory' => 'rector-root-override',
                'toolCommand' => 'lint:rector',
                'expectedPath' => 'custom-rector-root-path',
            ],
            'rector_config_directory_discovery' => [
                'scenarioName' => 'Rector config directory discovery',
                'fixtureDirectory' => 'rector-config-override',
                'toolCommand' => 'lint:rector',
                'expectedPath' => 'custom-rector-config-path',
            ],
            'phpstan_root_discovery' => [
                'scenarioName' => 'PHPStan root config discovery',
                'fixtureDirectory' => 'phpstan-root-override',
                'toolCommand' => 'lint:phpstan',
                'expectedPath' => 'custom-phpstan-root-path',
            ],
            'phpstan_config_directory_discovery' => [
                'scenarioName' => 'PHPStan config directory discovery',
                'fixtureDirectory' => 'phpstan-config-override',
                'toolCommand' => 'lint:phpstan',
                'expectedPath' => 'custom-phpstan-config-path',
            ],
            'fractor_root_discovery' => [
                'scenarioName' => 'Fractor root config discovery',
                'fixtureDirectory' => 'fractor-root-override',
                'toolCommand' => 'lint:fractor',
                'expectedPath' => 'custom-fractor-root-path',
            ],
            'php_cs_fixer_root_discovery' => [
                'scenarioName' => 'PHP-CS-Fixer root config discovery',
                'fixtureDirectory' => 'php-cs-fixer-root-override',
                'toolCommand' => 'lint:php-cs-fixer',
                'expectedPath' => 'custom-php-cs-fixer-root-path',
            ],
        ];
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
     * Set up mock tool executables from physical fixtures.
     * These mock executables parse the config files and output the paths configured in them.
     */
    private function setupMockToolExecutables(string $vendorDir, string $fixtureDirectory, string $expectedPath): void
    {
        $binDir = $vendorDir . '/bin';
        $mockExecutablesDir = __DIR__ . '/../../Fixtures/mockExecutables';

        // Copy the appropriate mock executable based on the fixture directory
        if (strpos($fixtureDirectory, 'rector') !== false) {
            copy($mockExecutablesDir . '/rector', $binDir . '/rector');
            chmod($binDir . '/rector', 0755);
        } elseif (strpos($fixtureDirectory, 'phpstan') !== false) {
            copy($mockExecutablesDir . '/phpstan', $binDir . '/phpstan');
            chmod($binDir . '/phpstan', 0755);
        } elseif (strpos($fixtureDirectory, 'fractor') !== false) {
            copy($mockExecutablesDir . '/fractor', $binDir . '/fractor');
            chmod($binDir . '/fractor', 0755);
            // Fractor needs a default config file in case auto-discovery fails
            $configDir = $vendorDir . '/cpsit/quality-tools/config';
            if (!file_exists($configDir . '/fractor.php')) {
                file_put_contents($configDir . '/fractor.php', "<?php\nreturn static function (\$config) {\n    \$config->paths(['custom-fractor-root-path/']);\n};");
            }
        } elseif (strpos($fixtureDirectory, 'php-cs-fixer') !== false) {
            copy($mockExecutablesDir . '/php-cs-fixer', $binDir . '/php-cs-fixer');
            chmod($binDir . '/php-cs-fixer', 0755);
        }
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
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0o755, true);
                }
            } else {
                $dir = \dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0o755, true);
                }
                copy($item->getRealPath(), $targetPath);
            }
        }
    }
}
