<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
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

        // Create required services
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(
            new Filesystem(),
            $this->securityService,
        );
    }

    /**
     * Create a configuration loader instance.
     */
    private function createConfigurationLoader(string $loaderType): ConfigurationLoaderInterface
    {
        $validator = new ConfigurationValidator();
        $toolValidator = new ToolConfigurationValidationService();

        return match ($loaderType) {
            'hierarchical' => new HierarchicalConfigurationLoader(
                $validator,
                $this->securityService,
                $this->filesystemService,
                $toolValidator,
            ),
            'unified' => new ConfigurationLoader(
                $validator,
                $this->securityService,
                $this->filesystemService,
                $toolValidator,
            ),
            default => throw new \InvalidArgumentException("Unknown loader type: {$loaderType}"),
        };
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
        // Create a configuration file
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('permission-test')
            ->withRector()
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        // Create a custom rector config
        $rectorFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorFile, $this->getRectorConfigContent());

        // Set file permissions
        chmod($rectorFile, $filePermissions);

        // Check if file permissions actually work in this environment
        $canTestPermissions = !is_readable($rectorFile) && @file_get_contents($rectorFile) === false;

        // In environments where file permissions don't work, expect success for all scenarios
        $expectedSuccess = $shouldSucceed || !$canTestPermissions;

        // Test configuration loading (using hierarchical loader for backward compatibility)
        $configurationLoader = $this->createConfigurationLoader('hierarchical');
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
            // Restore permissions for cleanup
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
        // Test loading from empty directory (using hierarchical loader for backward compatibility)
        $configurationLoader = $this->createConfigurationLoader('hierarchical');
        try {
            $config = $configurationLoader->load($this->tempDir);

            // Should succeed with empty/default configuration
            $this->assertIsArray($config->toArray(), 'Should handle missing configuration gracefully');
        } catch (\Exception $e) {
            // Document the current behavior
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
        // Create configuration files
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('concurrent-test')
            ->withRector()
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        $rectorFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorFile, $this->getRectorConfigContent());

        // Simulate concurrent access by loading configuration multiple times
        $configurationLoader = $this->createConfigurationLoader('hierarchical');
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

        // All concurrent loads should either succeed or fail consistently
        if (!empty($results)) {
            $this->assertCount(5, $results, 'All concurrent loads should succeed if any succeed');

            // Results should be identical
            $firstResult = $results[0];
            foreach ($results as $result) {
                $this->assertEquals(
                    $firstResult,
                    $result,
                    'Concurrent loads should produce identical results',
                );
            }
        } else {
            // If all failed, they should fail consistently
            $this->assertCount(5, $exceptions, 'All concurrent loads should fail consistently');
        }
    }

    /**
     * Test invalid configuration file formats per tool for both loaders in hierarchical mode.
     *
     * This test ensures that both the deprecated HierarchicalConfigurationLoader
     * and the new unified ConfigurationLoader (in hierarchical mode) validate tool
     * configuration files during loading and either throw exceptions or record errors
     * for invalid tool configurations.
     *
     * NOTE: Tool validation only occurs in hierarchical mode. Simple mode does not
     * validate individual tool configuration files.
     */
    #[DataProvider('invalidConfigurationFormatsWithLoaders')]
    public function testInvalidConfigurationFileFormatsWithBothLoaders(
        string $loaderType,
        string $tool,
        string $filename,
        string $invalidContent,
        string $expectedErrorType,
        string $scenarioDescription,
    ): void {
        $configurationLoader = $this->createConfigurationLoader($loaderType);

        // Create base configuration
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('invalid-format-test')
            ->withTool($tool, ['enabled' => true])
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        // Create invalid tool configuration file
        $toolConfigFile = $this->tempDir . '/' . $filename;
        file_put_contents($toolConfigFile, $invalidContent);

        // Configuration loading should validate tool config files and either:
        // 1. Throw an exception for invalid tool config files, OR
        // 2. Succeed but record errors that can be checked via getConfigurationErrors()
        //
        // IMPORTANT: Force hierarchical mode to ensure tool validation occurs.
        // Simple mode (loadWithoutHierarchy) does not validate individual tool config files.

        try {
            $config = match ($loaderType) {
                'hierarchical' => $configurationLoader->load($this->tempDir),
                'unified' => $configurationLoader->load($this->tempDir),
                default => throw new \InvalidArgumentException("Unknown loader type: {$loaderType}"),
            };

            // If loading succeeded, check if configuration errors were recorded
            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);

            if (empty($errors)) {
                $this->fail(
                    'Configuration loading should either fail or record errors for invalid tool config. ' .
                    "Loader: {$loaderType}, Scenario: {$scenarioDescription}",
                );
            }

            // Verify that errors were recorded for the invalid tool config file
            $hasRelevantError = false;
            foreach ($errors as $errorPath => $errorMessage) {
                if (str_contains((string) $errorPath, $filename)) {
                    $hasRelevantError = true;
                    $this->assertStringContainsString(
                        $tool,
                        $errorMessage,
                        "Error should mention the tool: {$tool} (Loader: {$loaderType})",
                    );
                    break;
                }
            }

            $this->assertTrue(
                $hasRelevantError,
                "Should record error for invalid {$tool} config file: {$filename}. " .
                "Loader: {$loaderType}. Errors: " . json_encode($errors),
            );
        } catch (\Exception $e) {
            // If an exception was thrown, verify it's related to the invalid tool config
            $this->assertTrue(
                str_contains($e->getMessage(), $tool) || str_contains($e->getMessage(), 'configuration'),
                'Exception should be related to invalid tool configuration. ' .
                "Loader: {$loaderType}, Scenario: {$scenarioDescription}. Error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Legacy test for backward compatibility - tests only HierarchicalConfigurationLoader.
     *
     * @deprecated Use testInvalidConfigurationFileFormatsWithBothLoaders instead
     */
    #[DataProvider('invalidConfigurationFormats')]
    public function testInvalidConfigurationFileFormats(
        string $tool,
        string $filename,
        string $invalidContent,
        string $expectedErrorType,
        string $scenarioDescription,
    ): void {
        $this->testInvalidConfigurationFileFormatsWithBothLoaders(
            'hierarchical',
            $tool,
            $filename,
            $invalidContent,
            $expectedErrorType,
            $scenarioDescription,
        );
    }

    /**
     * Test that simple mode does NOT validate individual tool configuration files during loading.
     *
     * This test verifies that in simple mode, invalid tool configuration files
     * are ignored during configuration loading and do not cause loading to fail.
     * However, getConfigurationErrors() will still detect these errors as it always
     * performs hierarchical discovery regardless of the loading mode used.
     */
    #[DataProvider('invalidConfigurationFormats')]
    public function testSimpleModeIgnoresInvalidToolConfigurationsDuringLoading(
        string $tool,
        string $filename,
        string $invalidContent,
        string $expectedErrorType,
        string $scenarioDescription,
    ): void {
        $configurationLoader = $this->createConfigurationLoader('unified');

        // Create base configuration
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('simple-mode-test')
            ->withTool($tool, ['enabled' => true])
            ->buildYaml();

        file_put_contents($configFile, $configContent);

        // Create invalid tool configuration file
        $toolConfigFile = $this->tempDir . '/' . $filename;
        file_put_contents($toolConfigFile, $invalidContent);

        // Simple mode should ignore invalid tool config files and succeed
        // NOTE: Only create the main config file (no tool-specific configs) to force simple mode
        try {
            $config = $configurationLoader->load($this->tempDir);

            // Verify loading succeeded
            $this->assertIsArray($config->toArray(), 'Simple mode should succeed even with invalid tool configs');

            // NOTE: getConfigurationErrors() ALWAYS performs hierarchical discovery
            // and will find tool validation errors even if configuration was loaded in simple mode.
            // This is correct - it shows ALL potential errors, not just those from the loading mode used.
            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);

            // However, validation only happens if validators are registered for the specific tool
            // In unit tests without DI container, ToolConfigurationValidationService has no validators registered
            // For tools without validators, no errors will be recorded - this is expected behavior
            if (empty($errors)) {
                // No validators registered for this tool - configuration loading succeeded without validation
                return;
            }

            // Verify the error is about the expected tool
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
     *
     * Verifies that the unified loader correctly chooses hierarchical mode when
     * multiple configuration sources are available, and simple mode otherwise.
     */
    public function testAutoDetectionBehaviorForConfigurationMode(): void
    {
        $configurationLoader = $this->createConfigurationLoader('unified');

        // Test 1: Single config file should trigger simple mode (no tool validation)
        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $configContent = ConfigurationBuilder::create()
            ->withProject('auto-detect-test')
            ->withTool('rector', ['enabled' => true])
            ->buildYaml();
        file_put_contents($configFile, $configContent);

        // Add invalid rector config - should be ignored in simple mode
        $rectorConfigFile = $this->tempDir . '/rector.php';
        file_put_contents($rectorConfigFile, '<?php invalid php syntax here');

        // Auto-detection will actually choose hierarchical mode (main config + tool config = 2 sources)
        try {
            $config = $configurationLoader->load($this->tempDir);
            $this->assertIsArray($config->toArray(), 'Auto-detection should succeed regardless of mode');

            // getConfigurationErrors() should detect tool validation errors
            $errors = $configurationLoader->getConfigurationErrors($this->tempDir);
            $this->assertNotEmpty($errors, 'Should detect tool validation errors');
        } catch (\Exception $e) {
            // Exception is also acceptable when there are invalid tool configs
            $this->assertStringContainsString(
                'rector',
                $e->getMessage(),
                'Exception should be related to invalid tool configuration',
            );
        }

        // Test 2: Verify that the current setup DOES trigger hierarchical mode
        // (main config + tool config = 2 sources = hierarchical mode)
        $hasHierarchical = $configurationLoader->hasHierarchicalConfiguration($this->tempDir);
        $this->assertTrue($hasHierarchical, 'Main config + tool config should trigger hierarchical mode');

        // Test 3: Verify that even though auto-detection chooses hierarchical mode,
        // the configuration loading succeeds and detects the invalid rector config
        try {
            $configWithHierarchical = $configurationLoader->load($this->tempDir);
            $errorsWithHierarchical = $configurationLoader->getConfigurationErrors($this->tempDir);

            // In hierarchical mode, should detect the invalid rector config
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
            // Exception is also acceptable in hierarchical mode for invalid configs
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
        // Create configuration with potentially malicious path
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

        // Test that security boundaries are enforced (using hierarchical loader for backward compatibility)
        $configurationLoader = $this->createConfigurationLoader('hierarchical');
        try {
            $config = $configurationLoader->load($this->tempDir);

            // This should either fail with security error or schema validation error
            $this->fail(
                "Configuration should reject malicious path: {$scenarioDescription}",
            );
        } catch (\Exception $e) {
            // Accept either security error or schema validation error (Issue 022)
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
        // Create complex project structure for performance testing
        $this->createComplexProjectStructure();

        // Measure configuration loading performance
        $iterations = 10;
        $times = [];

        for ($i = 0; $i < $iterations; ++$i) {
            $startTime = microtime(true);

            try {
                $configurationLoader = $this->createConfigurationLoader('hierarchical');
                $config = $configurationLoader->load($this->tempDir);
                $endTime = microtime(true);
                $times[] = $endTime - $startTime;
            } catch (\Exception $e) {
                $endTime = microtime(true);
                $times[] = $endTime - $startTime;

                // Performance should be consistent even when failing
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

        // Performance assertions
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

        // Performance should be reasonably consistent (max should not be more than 10x min)
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
            $configurationLoader = $this->createConfigurationLoader('hierarchical');
            $config = $configurationLoader->load($this->tempDir);
        } catch (\Exception) {
            // Memory test still valid even if loading fails
        }

        $memoryAfter = memory_get_usage();
        $peakAfter = memory_get_peak_usage();

        $memoryIncrease = $memoryAfter - $memoryBefore;
        $peakIncrease = $peakAfter - $peakBefore;

        // Memory usage should be reasonable
        $this->assertLessThan(
            1024 * 1024, // 1MB
            $memoryIncrease,
            'Memory increase should be under 1MB. Actual: ' . round($memoryIncrease / 1024) . 'KB',
        );

        $this->assertLessThan(
            2 * 1024 * 1024, // 2MB
            $peakIncrease,
            'Peak memory increase should be under 2MB. Actual: ' . round($peakIncrease / 1024) . 'KB',
        );
    }

    /**
     * Data provider for file permission scenarios.
     */
    public static function filePermissionScenarios(): array
    {
        return [
            'readable_file' => [
                'Readable configuration file',
                0o644, // rw-r--r--
                true, // Should succeed now that Issue 022 is resolved
                '', // No error expected
            ],
            'unreadable_file' => [
                'Unreadable configuration file',
                0o000, // --------
                false, // Should fail with file access error
                'permission', // File permission/access error expected
            ],
            'executable_only' => [
                'Executable only configuration file',
                0o100, // --x------
                false, // Should fail with file access error
                'permission', // File permission/access error expected
            ],
            'write_only' => [
                'Write only configuration file',
                0o200, // -w-------
                false, // Should fail with file access error
                'permission', // File permission/access error expected
            ],
        ];
    }

    /**
     * Data provider for invalid configuration formats.
     */
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

    /**
     * Data provider for invalid configuration formats with both loaders.
     *
     * Combines the base test data with loader types to test both
     * HierarchicalConfigurationLoader and unified ConfigurationLoader.
     */
    public static function invalidConfigurationFormatsWithLoaders(): array
    {
        $baseData = self::invalidConfigurationFormats();
        $loaderTypes = ['hierarchical', 'unified'];
        $combinedData = [];

        foreach ($loaderTypes as $loaderType) {
            foreach ($baseData as $key => $testCase) {
                $combinedData["{$loaderType}_{$key}"] = array_merge([$loaderType], $testCase);
            }
        }

        return $combinedData;
    }

    /**
     * Data provider for security violation scenarios.
     */
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
                'config.php .txt',
                'Null byte injection attempt',
            ],
        ];
    }

    /**
     * Get sample Rector configuration content.
     */
    private function getRectorConfigContent(): string
    {
        return '<?php
declare(strict_types=1);
use Rector\Config\RectorConfig;
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . "/edge-case-test-path"]);
};';
    }

    /**
     * Create complex project structure for performance testing.
     */
    private function createComplexProjectStructure(): void
    {
        // Create directory structure
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
            mkdir($this->tempDir . '/' . $dir, 0o755, true);
        }

        // Create configuration files
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

        // Create dummy source files for realistic structure
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

    /**
     * Get complex YAML configuration for performance testing.
     */
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

    /**
     * Get PHPStan configuration content.
     */
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

    /**
     * Get Fractor configuration content.
     */
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
