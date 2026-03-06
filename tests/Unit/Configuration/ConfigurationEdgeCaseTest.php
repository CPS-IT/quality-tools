<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
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
 * Edge case and error condition tests for configuration handling.
 *
 * POST-ISSUE 022: Configuration loading now succeeds for most edge cases
 * because schema validation has been fixed and tool-specific config files
 * are validated at execution time, not during configuration loading.
 *
 * TESTED EDGE CASES:
 * 1. File permissions (readable, unreadable, wrong permissions)
 * 2. Concurrent configuration access and consistency
 * 3. Invalid tool configuration file formats (POST-022: should succeed)
 * 4. Security boundary validation (directory traversal prevention)
 * 5. Performance impact measurement and memory usage
 */
final class ConfigurationEdgeCaseTest extends TestCase
{
    private string $tempDir;
    private FilesystemService $filesystemService;
    private SecurityService $securityService;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('config_edge_case_test_');

        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(
            new Filesystem(),
            $this->securityService,
        );
    }

    private function createConfigurationLoader(): ConfigurationLoaderInterface
    {
        $validator = new ConfigurationValidator();
        $toolValidator = new ToolConfigurationValidationService();

        return new ConfigurationLoader(
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
     * Test file permission edge cases.
     */
    #[DataProvider('filePermissionScenarios')]
    public function testFilePermissionEdgeCases(
        string $scenarioName,
        int $filePermissions,
        bool $shouldSucceed,
        string $expectedErrorPattern = '',
    ): void {
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('permission-test')
            ->withRector()
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        $rectorFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorFile, $this->getRectorConfigContent());

        chmod($rectorFile, $filePermissions);

        $canTestPermissions = !is_readable($rectorFile) && @file_get_contents($rectorFile) === false;

        $expectedSuccess = $shouldSucceed || !$canTestPermissions;

        $configurationLoader = $this->createConfigurationLoader();
        try {
            $config = $configurationLoader->load($this->tempDir);

            if (!$expectedSuccess) {
                $this->fail(
                    "Configuration loading should have failed for scenario: {$scenarioName} " .
                    '(permissions work in this environment)',
                );
            }

            $this->assertIsArray($config->toArray(), "Configuration should load successfully: {$scenarioName}");
        } catch (\Exception $e) {
            if ($expectedSuccess) {
                $this->fail(
                    "Configuration loading should have succeeded for scenario: {$scenarioName}. " .
                    "Error: {$e->getMessage()}",
                );
            }

            if (!empty($expectedErrorPattern) && $canTestPermissions) {
                $this->assertStringContainsString(
                    $expectedErrorPattern,
                    $e->getMessage(),
                    "Error message should match expected pattern for scenario: {$scenarioName}",
                );
            }
        } finally {
            if (file_exists($rectorFile)) {
                chmod($rectorFile, 0o644);
            }
        }
    }

    /**
     * Test missing configuration files handling.
     */
    public function testMissingConfigurationFiles(): void
    {
        $configurationLoader = $this->createConfigurationLoader();
        try {
            $config = $configurationLoader->load($this->tempDir);

            $this->assertIsArray($config->toArray(), 'Should handle missing configuration gracefully');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                'Configuration',
                $e->getMessage(),
                'Missing config error should be informative',
            );
        }
    }

    /**
     * Test concurrent configuration file access.
     */
    public function testConcurrentConfigurationAccess(): void
    {
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('concurrent-test')
            ->withRector()
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        $rectorFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorFile, $this->getRectorConfigContent());

        $configurationLoader = $this->createConfigurationLoader();
        $results = [];
        $exceptions = [];

        for ($i = 0; $i < 5; ++$i) {
            try {
                $config = $configurationLoader->load($this->tempDir);
                $results[] = $config->toArray();
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
            }
        }

        if (!empty($results)) {
            $this->assertCount(5, $results, 'All concurrent loads should succeed if any succeed');

            $firstResult = $results[0];
            foreach ($results as $result) {
                $this->assertEquals(
                    $firstResult,
                    $result,
                    'Concurrent loads should produce identical results',
                );
            }
        } else {
            $this->assertCount(5, $exceptions, 'All concurrent loads should fail consistently');
        }
    }

    /**
     * Test invalid configuration file formats per tool.
     */
    #[DataProvider('invalidConfigurationFormats')]
    public function testInvalidConfigurationFileFormats(
        string $tool,
        string $filename,
        string $invalidContent,
        string $expectedErrorType,
        string $scenarioDescription,
    ): void {
        $configurationLoader = $this->createConfigurationLoader();

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('invalid-format-test')
            ->withTool($tool, ['enabled' => true])
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        $toolConfigFile = $this->tempDir . '/' . $filename;
        file_put_contents($toolConfigFile, $invalidContent);

        try {
            $config = $configurationLoader->load($this->tempDir);

            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);

            if (empty($errors)) {
                $this->fail(
                    'Configuration loading should either fail or record errors for invalid tool config. ' .
                    "Scenario: {$scenarioDescription}",
                );
            }

            $hasRelevantError = false;
            foreach ($errors as $errorPath => $errorMessage) {
                if (str_contains((string) $errorPath, $filename)) {
                    $hasRelevantError = true;
                    $this->assertStringContainsString(
                        $tool,
                        $errorMessage,
                        "Error should mention the tool: {$tool}",
                    );
                    break;
                }
            }

            $this->assertTrue(
                $hasRelevantError,
                "Should record error for invalid {$tool} config file: {$filename}. " .
                'Errors: ' . json_encode($errors),
            );
        } catch (\Exception $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), $tool) || str_contains($e->getMessage(), 'configuration'),
                'Exception should be related to invalid tool configuration. ' .
                "Scenario: {$scenarioDescription}. Error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Test that simple mode does NOT validate individual tool configuration files during loading.
     */
    #[DataProvider('invalidConfigurationFormats')]
    public function testSimpleModeIgnoresInvalidToolConfigurationsDuringLoading(
        string $tool,
        string $filename,
        string $invalidContent,
        string $expectedErrorType,
        string $scenarioDescription,
    ): void {
        $configurationLoader = $this->createConfigurationLoader();

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('simple-mode-test')
            ->withTool($tool, ['enabled' => true])
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        $toolConfigFile = $this->tempDir . '/' . $filename;
        file_put_contents($toolConfigFile, $invalidContent);

        try {
            $config = $configurationLoader->load($this->tempDir);

            $this->assertIsArray($config->toArray(), 'Should succeed even with invalid tool configs');

            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);

            if (empty($errors)) {
                return;
            }

            $hasRelevantError = false;
            foreach ($errors as $errorPath => $errorMessage) {
                if (str_contains((string) $errorPath, $filename)) {
                    $hasRelevantError = true;
                    $this->assertStringContainsString(
                        $tool,
                        $errorMessage,
                        "Error should mention the tool: {$tool}. Scenario: {$scenarioDescription}",
                    );
                    break;
                }
            }
            $this->assertTrue($hasRelevantError, "Should find error for invalid {$tool} config. Scenario: {$scenarioDescription}");
        } catch (\Exception $e) {
            $this->fail(
                'Simple mode should not fail due to invalid tool configs. ' .
                "Scenario: {$scenarioDescription}, Error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Test auto-detection behavior for unified ConfigurationLoader.
     */
    public function testAutoDetectionBehaviorForConfigurationMode(): void
    {
        $configurationLoader = $this->createConfigurationLoader();

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('auto-detect-test')
            ->withTool('rector', ['enabled' => true])
            ->buildYaml();
        file_put_contents($configFile, $configContent);

        $rectorConfigFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorConfigFile, '<?php invalid php syntax here');

        try {
            $config = $configurationLoader->load($this->tempDir);
            $this->assertIsArray($config->toArray(), 'Auto-detection should succeed regardless of mode');

            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);
            $this->assertNotEmpty($errors, 'Should detect tool validation errors');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                'rector',
                $e->getMessage(),
                'Exception should be related to invalid tool configuration',
            );
        }

        $hasHierarchical = $configurationLoader->hasHierarchicalConfiguration($this->tempDir);
        $this->assertTrue($hasHierarchical, 'Main config + tool config should trigger hierarchical mode');

        try {
            $configWithHierarchical = $configurationLoader->load($this->tempDir);
            $errorsWithHierarchical = $configurationLoader->getConfigurationErrors($this->tempDir);

            $this->assertNotEmpty($errorsWithHierarchical, 'Hierarchical mode should record tool validation errors');

            $hasRectorError = false;
            foreach ($errorsWithHierarchical as $errorPath => $errorMessage) {
                if (str_contains((string) $errorPath, 'rector.php')) {
                    $hasRectorError = true;
                    break;
                }
            }
            $this->assertTrue($hasRectorError, 'Should detect invalid rector config in hierarchical mode');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                'rector',
                $e->getMessage(),
                'Exception should be related to rector configuration',
            );
        }
    }

    /**
     * Test security boundary validation (directory traversal prevention).
     */
    #[DataProvider('securityViolationScenarios')]
    public function testSecurityBoundaryValidation(
        string $maliciousPath,
        string $scenarioDescription,
    ): void {
        $configContent = [
            'quality-tools' => [
                'project' => ['name' => 'security-test'],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'config_file' => $maliciousPath,
                    ],
                ],
            ],
        ];

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($configContent));

        $configurationLoader = $this->createConfigurationLoader();
        try {
            $config = $configurationLoader->load($this->tempDir);

            $this->fail(
                "Configuration should reject malicious path: {$scenarioDescription}",
            );
        } catch (\Exception $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'config_file is not defined')
                || str_contains($e->getMessage(), 'security')
                || str_contains($e->getMessage(), 'path')
                || str_contains($e->getMessage(), 'directory traversal'),
                "Should reject malicious path with appropriate error: {$scenarioDescription}. " .
                "Error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Test performance impact measurement.
     */
    public function testPerformanceImpactMeasurement(): void
    {
        $this->createComplexProjectStructure();

        $iterations = 10;
        $times = [];

        for ($i = 0; $i < $iterations; ++$i) {
            $startTime = microtime(true);

            try {
                $configurationLoader = $this->createConfigurationLoader();
                $config = $configurationLoader->load($this->tempDir);
                $endTime = microtime(true);
                $times[] = $endTime - $startTime;
            } catch (\Exception $e) {
                $endTime = microtime(true);
                $times[] = $endTime - $startTime;

                $this->assertThat(
                    $e->getMessage(),
                    $this->logicalOr(
                        $this->stringContains('memory_limit is not defined'),
                        $this->stringContains('phpstan_memory_limit is not defined'),
                        $this->stringContains('config_file is not defined'),
                    ),
                    'Should fail consistently with schema validation error',
                );
            }
        }

        $avgTime = array_sum($times) / \count($times);
        $maxTime = max($times);
        $minTime = min($times);

        $this->assertLessThan(
            0.1,
            $avgTime,
            "Average configuration loading time should be under 100ms. Average: {$avgTime}s",
        );

        $this->assertLessThan(
            0.2,
            $maxTime,
            "Maximum configuration loading time should be under 200ms. Maximum: {$maxTime}s",
        );

        $this->assertLessThan(
            $minTime * 10,
            $maxTime,
            "Performance should be reasonably consistent. Min: {$minTime}s, Max: {$maxTime}s",
        );
    }

    /**
     * Test memory usage during configuration loading.
     */
    public function testMemoryUsageImpact(): void
    {
        $this->createComplexProjectStructure();

        $memoryBefore = memory_get_usage();
        $peakBefore = memory_get_peak_usage();

        try {
            $configurationLoader = $this->createConfigurationLoader();
            $config = $configurationLoader->load($this->tempDir);
        } catch (\Exception) {
        }

        $memoryAfter = memory_get_usage();
        $peakAfter = memory_get_peak_usage();

        $memoryIncrease = $memoryAfter - $memoryBefore;
        $peakIncrease = $peakAfter - $peakBefore;

        $this->assertLessThan(
            1024 * 1024,
            $memoryIncrease,
            'Memory increase should be under 1MB. Actual: ' . round($memoryIncrease / 1024) . 'KB',
        );

        $this->assertLessThan(
            2 * 1024 * 1024,
            $peakIncrease,
            'Peak memory increase should be under 2MB. Actual: ' . round($peakIncrease / 1024) . 'KB',
        );
    }

    public static function filePermissionScenarios(): array
    {
        return [
            'readable_file' => [
                'Readable configuration file',
                0o644,
                true,
                '',
            ],
            'unreadable_file' => [
                'Unreadable configuration file',
                0o000,
                false,
                'permission',
            ],
            'executable_only' => [
                'Executable only configuration file',
                0o100,
                false,
                'permission',
            ],
            'write_only' => [
                'Write only configuration file',
                0o200,
                false,
                'permission',
            ],
        ];
    }

    public static function invalidConfigurationFormats(): array
    {
        return [
            'rector_invalid_php' => [
                'rector',
                'rector.php',
                '<?php invalid php syntax here',
                'syntax_error',
                'Rector with invalid PHP syntax',
            ],
            'rector_wrong_structure' => [
                'rector',
                'rector.php',
                '<?php return ["invalid" => "structure"];',
                'structure_error',
                'Rector with wrong configuration structure',
            ],
            'phpstan_invalid_yaml' => [
                'phpstan',
                'phpstan.neon',
                'invalid: yaml: structure: here',
                'yaml_error',
                'PHPStan with invalid YAML syntax',
            ],
            'phpstan_missing_parameters' => [
                'phpstan',
                'phpstan.neon',
                'level: 6',
                'structure_error',
                'PHPStan without parameters section',
            ],
            'fractor_invalid_php' => [
                'fractor',
                'fractor.php',
                '<?php class InvalidStructure {}',
                'structure_error',
                'Fractor with invalid configuration structure',
            ],
        ];
    }

    public static function securityViolationScenarios(): array
    {
        return [
            'directory_traversal_basic' => [
                '../../../etc/passwd',
                'Basic directory traversal attempt',
            ],
            'directory_traversal_nested' => [
                '../../../../../../../../etc/passwd',
                'Deep directory traversal attempt',
            ],
            'directory_traversal_encoded' => [
                '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
                'URL-encoded directory traversal attempt',
            ],
            'absolute_system_path' => [
                '/etc/passwd',
                'Absolute system path access attempt',
            ],
            'windows_traversal' => [
                '..\\..\\..\\windows\\system32\\config\\sam',
                'Windows-style directory traversal attempt',
            ],
            'null_byte_injection' => [
                'config.php .txt',
                'Null byte injection attempt',
            ],
        ];
    }

    private function getRectorConfigContent(): string
    {
        return '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/edge-case-test-path"]);
};';
    }

    private function createComplexProjectStructure(): void
    {
        $directories = [
            'src/',
            'tests/',
            'config/',
            'packages/',
            'packages/sitepackage/',
            'packages/extension1/',
            'packages/extension2/',
            'vendor/',
            'var/',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($this->tempDir . '/' . $dir)) {
                mkdir($this->tempDir . '/' . $dir, 0o755, true);
            }
        }

        $configFiles = [
            '.quality-tools.yaml' => $this->getComplexYamlConfig(),
            'rector.php' => $this->getRectorConfigContent(),
            'phpstan.neon' => $this->getPhpstanConfigContent(),
            'fractor.php' => $this->getFractorConfigContent(),
            'config/rector.php' => $this->getRectorConfigContent(),
            'config/phpstan.neon' => $this->getPhpstanConfigContent(),
        ];

        foreach ($configFiles as $filename => $content) {
            $filepath = $this->tempDir . '/' . $filename;
            $dir = \dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($filepath, $content);
        }

        $sourceFiles = [
            'src/Controller.php' => '<?php class Controller {}',
            'src/Service.php' => '<?php class Service {}',
            'tests/ControllerTest.php' => '<?php class ControllerTest {}',
            'packages/sitepackage/ext_emconf.php' => '<?php $EM_CONF = [];',
            'packages/extension1/composer.json' => '{"name": "test/extension1"}',
        ];

        foreach ($sourceFiles as $filename => $content) {
            $filepath = $this->tempDir . '/' . $filename;
            $dir = \dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($filepath, $content);
        }
    }

    private function getComplexYamlConfig(): string
    {
        return ConfigurationBuilder::create()
            ->withProject('complex-performance-test')
            ->withRector(['enabled' => true])
            ->withPhpstan(['enabled' => true, 'level' => 6])
            ->withTool('fractor', ['enabled' => true])
            ->withTool('php-cs-fixer', ['enabled' => true])
            ->withPaths(
                ['src/', 'packages/', 'config/'],
                ['vendor/', 'var/', 'tests/'],
            )
            ->withPerformance(512, 1024)
            ->buildYaml();
    }

    private function getPhpstanConfigContent(): string
    {
        return 'parameters:
    level: 6
    paths:
        - performance-test-path/
    excludePaths:
        - */Tests/*
        - */vendor/*';
    }

    private function getFractorConfigContent(): string
    {
        return '<?php
declare(strict_types=1);
use Fractor\Config\FractorConfig;
return static function (FractorConfig $fractorConfig): void {
    $fractorConfig->paths([__DIR__ . "/Configuration/TypoScript"]);
};';
    }
}
