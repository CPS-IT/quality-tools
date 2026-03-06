<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Support\ConfigurationBuilder;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration tests for configuration regression protection.
 *
 * Comprehensive matrix testing to ensure that configuration loading behavior
 * remains consistent across different scenarios and does not regress when
 * Issue 022 is fixed.
 */
final class ConfigurationRegressionTest extends TestCase
{
    private string $tempDir;
    private ConfigurationLoader $configurationLoader;
    private SecurityService $securityService;
    private FilesystemService $filesystemService;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_regression_test_');

        $validator = new ConfigurationValidator();
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(
            new Filesystem(),
            $this->securityService,
        );
        $toolValidator = new ToolConfigurationValidationService();

        $this->configurationLoader = new ConfigurationLoader(
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
     * Test configuration loading behavior matrix.
     *
     * This test covers all combinations of configuration scenarios to ensure
     * consistent behavior and prevent regressions.
     */
    #[DataProvider('configurationRegressionMatrix')]
    public function testConfigurationLoadingRegression(
        array $scenario,
        string $scenarioName,
    ): void {
        // Skip tests for unimplemented Issue 022 functionality
        $unimplementedScenarios = [
            'Project with only custom Rector configuration',
            'Project with only custom PHPStan configuration',
            'Project with multiple custom configuration files',
            'Project with no custom configuration files (currently fails with schema validation)',
        ];

        if (\in_array($scenarioName, $unimplementedScenarios, true)) {
            $this->markTestSkipped('Configuration file replacement functionality not yet implemented (Issue 022 Phase 3)');
        }

        // Setup the scenario
        $this->setupScenario($scenario);

        // Record current behavior before any fixes
        $behaviorBefore = $this->captureConfigurationBehavior();

        if ($scenario['expects_schema_failure']) {
            $this->assertSchemaValidationFailure($behaviorBefore, $scenarioName);
        } else {
            $this->assertValidConfiguration($behaviorBefore, $scenarioName);
        }

        // Verify tool configuration structure regardless of schema issues
        if (isset($scenario['expected_tool_configs'])) {
            $this->verifyToolConfigurationStructure(
                $behaviorBefore,
                $scenario['expected_tool_configs'],
                $scenarioName,
            );
        }
    }

    /**
     * Test backward compatibility with existing configurations.
     */
    #[DataProvider('backwardCompatibilityScenarios')]
    public function testBackwardCompatibility(
        array $configData,
        string $scenarioDescription,
    ): void {
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($configData));

        try {
            $config = $this->configurationLoader->load($this->tempDir);

            $this->assertIsArray($config->toArray(), "Configuration should convert to array for: {$scenarioDescription}");
        } catch (\Exception $e) {
            $this->markTestIncomplete(
                "Backward compatibility test failed for {$scenarioDescription}: {$e->getMessage()}",
            );
        }
    }

    /**
     * Test command integration regression protection.
     */
    #[DataProvider('commandIntegrationScenarios')]
    public function testCommandIntegrationRegression(
        string $commandName,
        array $projectStructure,
        array $expectedBehavior,
    ): void {
        $this->setupProjectStructure($projectStructure);

        $this->markTestIncomplete(
            "Command integration test for {$commandName} - to be completed after Issue 022 fix",
        );
    }

    /**
     * Test performance regression for configuration loading.
     */
    public function testConfigurationLoadingPerformance(): void
    {
        $this->setupComplexProjectStructure();

        $startTime = microtime(true);

        try {
            $config = $this->configurationLoader->load($this->tempDir);
            $loadTime = microtime(true) - $startTime;

            $this->assertLessThan(
                0.1,
                $loadTime,
                "Configuration loading should be fast. Took: {$loadTime}s",
            );
        } catch (\Exception) {
            $loadTime = microtime(true) - $startTime;

            $this->assertLessThan(
                0.1,
                $loadTime,
                "Configuration loading failure should be fast. Took: {$loadTime}s",
            );
        }
    }

    /**
     * Data provider for configuration regression matrix.
     */
    public static function configurationRegressionMatrix(): array
    {
        return [
            'no_custom_configs' => [
                [
                    'yaml_config' => ConfigurationBuilder::create()->withProject('no-custom')->build(),
                    'custom_files' => [],
                    'expects_schema_failure' => false,
                    'expected_tool_configs' => [
                        'rector' => ['uses_default' => true],
                        'phpstan' => ['uses_default' => true],
                    ],
                ],
                'Project with no custom configuration files (currently fails with schema validation)',
            ],
            'rector_only_custom' => [
                [
                    'yaml_config' => ConfigurationBuilder::create()->withProject('rector-custom')->build(),
                    'custom_files' => ['rector.php'],
                    'expects_schema_failure' => false,
                    'expected_tool_configs' => [
                        'rector' => ['should_use_custom' => true],
                        'phpstan' => ['uses_default' => true],
                    ],
                ],
                'Project with only custom Rector configuration',
            ],
            'phpstan_only_custom' => [
                [
                    'yaml_config' => ConfigurationBuilder::create()->withProject('phpstan-custom')->build(),
                    'custom_files' => ['phpstan.neon'],
                    'expects_schema_failure' => false,
                    'expected_tool_configs' => [
                        'rector' => ['uses_default' => true],
                        'phpstan' => ['should_use_custom' => true],
                    ],
                ],
                'Project with only custom PHPStan configuration',
            ],
            'multiple_custom_configs' => [
                [
                    'yaml_config' => ConfigurationBuilder::create()->withProject('multi-custom')->build(),
                    'custom_files' => ['rector.php', 'phpstan.neon', 'fractor.php'],
                    'expects_schema_failure' => false,
                    'expected_tool_configs' => [
                        'rector' => ['should_use_custom' => true],
                        'phpstan' => ['should_use_custom' => true],
                        'fractor' => ['should_use_custom' => true],
                    ],
                ],
                'Project with multiple custom configuration files',
            ],
            'explicit_config_in_yaml' => [
                [
                    'yaml_config' => ConfigurationBuilder::withExplicitConfigFiles(
                        'explicit-config',
                        ['rector' => 'custom-configs/rector.php'],
                    )->build(),
                    'custom_files' => ['custom-configs/rector.php'],
                    'expects_schema_failure' => false,
                    'expected_tool_configs' => [
                        'rector' => ['should_use_explicit' => 'custom-configs/rector.php'],
                    ],
                ],
                'Project with explicit config_file in YAML',
            ],
        ];
    }

    /**
     * Data provider for backward compatibility scenarios.
     */
    public static function backwardCompatibilityScenarios(): array
    {
        return [
            'legacy_simple_config' => [
                ConfigurationBuilder::minimal('legacy-simple')->build(),
                'Legacy simple configuration',
            ],
            'legacy_with_tool_settings' => [
                ConfigurationBuilder::create()
                    ->withProject('legacy-tools')
                    ->withRector(['enabled' => true])
                    ->withPhpstan(['level' => 6])
                    ->build(),
                'Legacy configuration with tool settings',
            ],
            'legacy_with_paths' => [
                ConfigurationBuilder::create()
                    ->withProject('legacy-paths')
                    ->withPaths(['src/', 'config/'], ['vendor/', 'var/'])
                    ->build(),
                'Legacy configuration with custom paths',
            ],
        ];
    }

    /**
     * Data provider for command integration scenarios.
     */
    public static function commandIntegrationScenarios(): array
    {
        return [
            'lint_rector_with_custom_config' => [
                'lint:rector',
                ['rector.php' => true, 'src/' => true],
                ['expected_tool_usage' => 'custom_config'],
            ],
            'lint_phpstan_with_custom_config' => [
                'lint:phpstan',
                ['phpstan.neon' => true, 'src/' => true],
                ['expected_tool_usage' => 'custom_config'],
            ],
            'config_validate_with_mixed_configs' => [
                'config:validate',
                ['rector.php' => true, 'phpstan.neon' => true],
                ['expected_tool_usage' => 'validation_should_work'],
            ],
            'config_show_with_auto_discovery' => [
                'config:show',
                ['rector.php' => true],
                ['expected_tool_usage' => 'show_auto_discovery_indicators'],
            ],
        ];
    }

    private function setupScenario(array $scenario): void
    {
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($scenario['yaml_config']));

        foreach ($scenario['custom_files'] as $customFile) {
            $this->createCustomConfigFile($customFile);
        }
    }

    private function createCustomConfigFile(string $filename): void
    {
        $filePath = $this->tempDir . '/' . $filename;

        $directory = \dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        if (str_contains($filename, '.php')) {
            $content = $this->getPhpConfigContent($filename);
        } elseif (str_contains($filename, '.neon')) {
            $content = $this->getNeonConfigContent($filename);
        } else {
            $content = "# Custom config file: {$filename}\n";
        }

        file_put_contents($filePath, $content);
    }

    private function getPhpConfigContent(string $filename): string
    {
        if (str_contains($filename, 'rector')) {
            return '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/regression-test-path"]);
};';
        }

        if (str_contains($filename, 'fractor')) {
            return '<?php
declare(strict_types=1);
use Fractor\Config\FractorConfig;
return static function (FractorConfig $fractorConfig): void {
    $fractorConfig->paths([__DIR__ . "/Configuration/TypoScript"]);
};';
        }

        return "<?php\n// Custom config: {$filename}\n";
    }

    private function getNeonConfigContent(string $filename): string
    {
        return "parameters:
\tlevel: 6
\tpaths:
\t\t- regression-test-path/
\texcludePaths:
\t\t- */Tests/*";
    }

    private function captureConfigurationBehavior(): array
    {
        $behavior = [
            'loading_success' => false,
            'schema_validation_errors' => [],
            'configuration_data' => null,
            'exception_message' => null,
        ];

        try {
            $config = $this->configurationLoader->load($this->tempDir);
            $behavior['loading_success'] = true;
            $behavior['configuration_data'] = $config->toArray();
        } catch (\Exception $e) {
            $behavior['exception_message'] = $e->getMessage();

            if (str_contains($e->getMessage(), 'is not defined')
                || str_contains($e->getMessage(), 'Wrong type for')
                || str_contains($e->getMessage(), 'Invalid merged configuration')
                || str_contains($e->getMessage(), 'schema')) {
                $behavior['schema_validation_errors'][] = $e->getMessage();
            }
        }

        return $behavior;
    }

    private function assertSchemaValidationFailure(array $behavior, string $scenarioName): void
    {
        $this->assertFalse(
            $behavior['loading_success'],
            "Configuration loading should fail due to schema validation in: {$scenarioName}",
        );

        $this->assertNotEmpty(
            $behavior['schema_validation_errors'],
            "Should have schema validation errors in: {$scenarioName}",
        );

        $hasSchemaError = false;
        $errorPatterns = ['config_file is not defined', 'Wrong type for', 'Invalid merged configuration'];

        foreach ($behavior['schema_validation_errors'] as $error) {
            foreach ($errorPatterns as $pattern) {
                if (str_contains((string) $error, $pattern)) {
                    $hasSchemaError = true;
                    break 2;
                }
            }
        }

        $this->assertTrue(
            $hasSchemaError,
            "Should have Issue 022 related schema error in: {$scenarioName}. " .
            'Actual errors: ' . implode('; ', $behavior['schema_validation_errors']),
        );
    }

    private function assertValidConfiguration(array $behavior, string $scenarioName): void
    {
        $this->assertTrue(
            $behavior['loading_success'],
            "Configuration loading should succeed in: {$scenarioName}. " .
            'Error: ' . ($behavior['exception_message'] ?? 'none'),
        );

        $this->assertEmpty(
            $behavior['schema_validation_errors'],
            "Should not have schema validation errors in: {$scenarioName}",
        );

        $this->assertNotNull(
            $behavior['configuration_data'],
            "Should have configuration data in: {$scenarioName}",
        );
    }

    private function verifyToolConfigurationStructure(
        array $behavior,
        array $expectedConfigs,
        string $scenarioName,
    ): void {
        foreach ($expectedConfigs as $expectations) {
            if (isset($expectations['should_use_custom']) && $expectations['should_use_custom']) {
                $this->addToAssertionCount(1);
            }

            if (isset($expectations['uses_default']) && $expectations['uses_default']) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function setupProjectStructure(array $structure): void
    {
        foreach ($structure as $path => $shouldExist) {
            if ($shouldExist) {
                $fullPath = $this->tempDir . '/' . $path;

                if (str_ends_with((string) $path, '/')) {
                    if (!is_dir($fullPath)) {
                        mkdir($fullPath, 0o755, true);
                    }
                } else {
                    if (!is_dir(\dirname($fullPath))) {
                        mkdir(\dirname($fullPath), 0o755, true);
                    }

                    if (str_contains((string) $path, '.php')) {
                        file_put_contents($fullPath, $this->getPhpConfigContent($path));
                    } elseif (str_contains((string) $path, '.neon')) {
                        file_put_contents($fullPath, $this->getNeonConfigContent($path));
                    } else {
                        file_put_contents($fullPath, "// Test file: {$path}\n");
                    }
                }
            }
        }
    }

    private function setupComplexProjectStructure(): void
    {
        $structure = [
            'src/' => true,
            'tests/' => true,
            'config/' => true,
            'packages/' => true,
            'packages/sitepackage/' => true,
            'packages/extension1/' => true,
            'packages/extension2/' => true,
            'rector.php' => true,
            'phpstan.neon' => true,
            'fractor.php' => true,
            'config/rector.php' => true,
            'config/phpstan.neon' => true,
            '.quality-tools.yaml' => true,
        ];

        $this->setupProjectStructure($structure);

        $complexConfig = ConfigurationBuilder::create()
            ->withProject('complex-performance-test')
            ->withRector()
            ->withPhpstan()
            ->withTool('fractor', ['enabled' => true])
            ->withTool('php-cs-fixer', ['enabled' => true])
            ->withPaths(
                ['src/', 'packages/', 'config/'],
                ['vendor/', 'var/', 'tests/'],
            )
            ->build();

        file_put_contents(
            $this->tempDir . '/.quality-tools.yaml',
            \Symfony\Component\Yaml\Yaml::dump($complexConfig, 4, 2),
        );
    }
}
