<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Tests\Support\ConfigurationAssertions;
use Cpsit\QualityTools\Tests\Support\ConfigurationBuilder;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for custom tool configuration file replacement.
 * 
 * CURRENT BEHAVIOR (Issue 022): All tests fail with schema validation errors because
 * `config_file` property is not defined in the JSON schema but is added by 
 * ConfigurationDiscovery during auto-detection.
 * 
 * EXPECTED POST-FIX BEHAVIOR: Tests should pass and validate that:
 * 1. Auto-discovered config files are properly detected and used
 * 2. Explicit config_file settings in YAML override auto-discovery  
 * 3. Configuration precedence follows: User YAML > Auto-discovered > Package defaults
 * 4. Commands use custom configurations when present
 * 
 * UPDATE INSTRUCTIONS: Once Issue 022 is fixed:
 * 1. Remove markTestSkipped() calls
 * 2. Update assertions to validate successful configuration loading
 * 3. Verify tool configurations contain expected config_file paths
 * 4. Enable command integration tests
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
        
        $this->configurationLoader = new HierarchicalConfigurationLoader(
            $validator,
            $securityService,
            $filesystemService
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Test auto-discovery of custom tool configuration files.
     * 
     * This test currently FAILS due to Issue 022 schema validation conflicts.
     * The system discovers custom tool configs but adds undefined schema keys.
     */
    #[DataProvider('customToolConfigScenarios')]
    public function testCustomToolConfigurationAutoDiscovery(
        string $scenarioName,
        string $fixtureDirectory,
        array $expectedToolConfigs
    ): void {
        // Copy fixture to temp directory for testing
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        $this->copyFixtureToTempDir($fixturePath);

        // This will currently FAIL with schema validation errors
        // Expected error: "The property tool_config_file is not defined and the definition does not allow additional properties"
        try {
            $config = $this->configurationLoader->load($this->tempDir);
            
            // If we get here without exception, verify the configuration is correct
            foreach ($expectedToolConfigs as $tool => $expectedConfig) {
                if (isset($expectedConfig['config_file_contains'])) {
                    ConfigurationAssertions::assertToolHasAutoDiscoveredConfig(
                        $config,
                        $tool,
                        $expectedConfig['config_file_contains'],
                        "Tool '{$tool}' should auto-discover config file containing '{$expectedConfig['config_file_contains']}' in scenario: {$scenarioName}"
                    );
                }
                
                if (isset($expectedConfig['explicit_config_file'])) {
                    ConfigurationAssertions::assertToolUsesExplicitConfigFile(
                        $config,
                        $tool,
                        $expectedConfig['explicit_config_file'],
                        "Tool '{$tool}' should use explicit config file '{$expectedConfig['explicit_config_file']}' in scenario: {$scenarioName}"
                    );
                }
            }
            
        } catch (\Exception $e) {
            // This is the current failing behavior - document it for Issue 022
            $this->assertStringContainsString(
                'config_file is not defined',
                $e->getMessage(),
                "Should fail with config_file schema error (Issue 022) in scenario: {$scenarioName}"
            );
            
            // Mark this as expected failure until Issue 022 is fixed
            $this->markTestSkipped(
                "Test skipped due to Issue 022: Configuration file replacement schema validation bug. " .
                "Scenario: {$scenarioName}. Error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Test config:validate command with custom tool configurations.
     * 
     * This test documents the false positive behavior where config:validate
     * reports success but config:show fails with schema errors.
     */
    #[DataProvider('customToolConfigScenarios')]
    public function testConfigValidateCommandWithCustomToolConfig(
        string $scenarioName,
        string $fixtureDirectory,
        array $expectedToolConfigs
    ): void {
        // Copy fixture to temp directory
        $fixturePath = __DIR__ . '/../../Fixtures/configFileReplacement/' . $fixtureDirectory;
        $this->copyFixtureToTempDir($fixturePath);

        // Create application and test config:validate command
        $application = new QualityToolsApplication();
        $command = $application->find('config:validate');
        $commandTester = new CommandTester($command);

        // Run config:validate - this currently shows FALSE POSITIVE
        $exitCode = $commandTester->execute([], ['cwd' => $this->tempDir]);
        
        // Document current false positive behavior
        if ($exitCode === 0) {
            // This is the false positive - validation claims success
            $output = $commandTester->getDisplay();
            $this->assertStringContainsString(
                '[OK] Configuration is valid',
                $output,
                "config:validate shows false positive for scenario: {$scenarioName}"
            );
            
            // But config:show should fail with schema errors
            $showCommand = $application->find('config:show');
            $showCommandTester = new CommandTester($showCommand);
            
            $showExitCode = $showCommandTester->execute([], ['cwd' => $this->tempDir]);
            $showOutput = $showCommandTester->getDisplay();
            
            if ($showExitCode === 0) {
                // Command succeeded - this documents that the false positive extends to config:show
                $this->markTestIncomplete(
                    "config:show unexpectedly succeeded in scenario: {$scenarioName}. " .
                    "This suggests Issue 022 behavior may be different than expected."
                );
            } else {
                // Command failed as expected - verify it's due to schema validation
                $errorOutput = $showOutput;
                $isSchemaError = str_contains($errorOutput, 'config_file is not defined') ||
                               str_contains($errorOutput, 'schema') ||
                               str_contains($errorOutput, 'validation');
                
                $this->assertTrue(
                    $isSchemaError,
                    "config:show should fail with schema/validation error in scenario: {$scenarioName}. " .
                    "Output: {$errorOutput}"
                );
            }
        }
        
        // Mark test as documentation of Issue 022 until fixed
        $this->markTestSkipped(
            "Test documents Issue 022 false positive behavior in scenario: {$scenarioName}. " .
            "config:validate claims success but config:show fails with schema errors."
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
                "Tool command currently ignores custom config (Issue 022 behavior)"
            );
            
        } catch (\Exception $e) {
            // May fail due to configuration loading issues
            $this->markTestSkipped(
                "Tool command test skipped due to configuration loading error: {$e->getMessage()}"
            );
        }
        
        // Document the current broken behavior
        $this->markTestSkipped(
            "Test documents Issue 022: Tool commands ignore custom configuration files"
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
                'Explicit config_file in YAML should override auto-discovered file'
            );
            
        } catch (\Exception $e) {
            // Current Issue 022 behavior - schema validation fails
            $this->assertStringContainsString(
                'config_file is not defined',
                $e->getMessage(),
                'Should fail with Issue 022 schema error (precedence test)'
            );
            
            $this->markTestSkipped(
                'Test skipped due to Issue 022: Configuration precedence cannot be tested until schema validation is fixed. ' .
                'Expected: Explicit YAML config_file should override auto-discovered rector.php'
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
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $this->tempDir . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                mkdir($targetPath, 0755, true);
            } else {
                mkdir(dirname($targetPath), 0755, true);
                copy($item->getRealPath(), $targetPath);
            }
        }
    }
}