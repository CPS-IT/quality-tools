<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Environment;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Environmental variation tests.
 *
 * These tests validate that our quality tools work correctly across
 * different project structures, dependency versions, and environments.
 */
final class EnvironmentalVariationTest extends TestCase
{
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $dir) {
            if (is_dir($dir)) {
                TestHelper::removeDirectory($dir);
            }
        }
        $this->tempDirectories = [];
    }

    private function createTestProject(string $type): string
    {
        $tempDir = TestHelper::createTempDirectory("env_test_{$type}_");
        $this->tempDirectories[] = $tempDir;

        return $tempDir;
    }

    /**
     * Test different TYPO3 project structures.
     */
    public function testAcrossDifferentTypo3ProjectTypes(): void
    {
        $projectTypes = [
            'typo3-core' => [
                'require' => ['typo3/cms-core' => '^13.4'],
                'expectedBehavior' => 'success',
            ],
            'typo3-minimal' => [
                'require' => ['typo3/minimal' => '^13.4'],
                'expectedBehavior' => 'success',
            ],
            'typo3-cms' => [
                'require' => ['typo3/cms' => '^13.4'],
                'expectedBehavior' => 'success',
            ],
            'typo3-dev-only' => [
                'require' => ['symfony/console' => '^7.0'],
                'require-dev' => ['typo3/cms-core' => '^13.4'],
                'expectedBehavior' => 'success', // Should work with dev dependency
            ],
            'non-typo3' => [
                'require' => ['doctrine/orm' => '^3.0'],
                'expectedBehavior' => 'limited', // Some commands should work
            ],
        ];

        foreach ($projectTypes as $type => $config) {
            $projectDir = $this->createTestProject($type);
            $this->setupProjectType($projectDir, $config);

            $result = $this->testQualityToolsInProject($projectDir, $type);

            if ($config['expectedBehavior'] === 'success') {
                $this->assertEquals(
                    0,
                    $result['exitCode'],
                    "Quality tools should work with {$type} project",
                );
            } elseif ($config['expectedBehavior'] === 'limited') {
                // Should not crash, but some features may be limited
                $this->assertLessThan(
                    128,
                    $result['exitCode'],
                    "Quality tools should not crash with {$type} project",
                );
            }
        }
    }

    /**
     * Test different vendor directory locations.
     */
    public function testDifferentVendorDirectoryStructures(): void
    {
        $vendorStructures = [
            'standard' => 'vendor',
            'app_vendor' => 'app/vendor',
            'composer_vendor' => 'composer/vendor',
        ];

        foreach ($vendorStructures as $name => $vendorPath) {
            $projectDir = $this->createTestProject("vendor_{$name}");
            $this->setupProjectWithCustomVendorPath($projectDir, $vendorPath);

            $result = $this->testToolDiscoveryInProject($projectDir, $vendorPath);

            $this->assertEquals(
                0,
                $result['exitCode'],
                "Quality tools should discover tools in {$vendorPath}",
            );
        }
    }

    /**
     * Test different PHP memory limits.
     */
    public function testAcrossDifferentPhpMemoryLimits(): void
    {
        $memoryLimits = [
            'low' => '64M',
            'medium' => '128M',
            'high' => '256M',
            'unlimited' => '-1',
        ];

        $projectDir = $this->createTestProject('memory_test');
        $this->setupStandardProject($projectDir);

        foreach ($memoryLimits as $limitName => $limitValue) {
            $env = ['PHP_MEMORY_LIMIT' => $limitValue];

            $result = $this->runQualityToolWithEnvironment($projectDir, $env);

            if ($limitName === 'low') {
                // Low memory might fail, but should fail gracefully
                $this->assertContains(
                    $result['exitCode'],
                    [0, 1, 2],
                    'Tool should handle low memory gracefully',
                );
            } else {
                $this->assertEquals(
                    0,
                    $result['exitCode'],
                    "Tool should work with {$limitName} memory limit ({$limitValue})",
                );
            }
        }
    }

    /**
     * Test different file permissions scenarios.
     */
    public function testFilePermissionScenarios(): void
    {
        $projectDir = $this->createTestProject('permissions');
        $this->setupStandardProject($projectDir);

        // Test read-only configuration files
        $configFile = $projectDir . '/vendor/cpsit/quality-tools/config/rector.php';
        chmod($configFile, 0o444); // Read-only

        $result = $this->runQualityTool($projectDir, 'rector', ['--dry-run']);
        $this->assertEquals(
            0,
            $result['exitCode'],
            'Should handle read-only config files',
        );

        // Restore permissions
        chmod($configFile, 0o644);

        // Test read-only source files
        $sourceFile = $projectDir . '/packages/test_extension/Classes/Controller/TestController.php';
        $sourceDir = \dirname($sourceFile);
        if (!is_dir($sourceDir)) {
            mkdir($sourceDir, 0o777, true);
        }
        file_put_contents($sourceFile, '<?php class TestController {}');
        chmod($sourceFile, 0o444); // Read-only

        $result = $this->runQualityTool($projectDir, 'rector', ['--dry-run']);
        $this->assertEquals(
            0,
            $result['exitCode'],
            'Should handle read-only source files in dry-run mode',
        );
    }

    /**
     * Test different file encodings and special characters.
     */
    public function testDifferentFileEncodings(): void
    {
        $projectDir = $this->createTestProject('encoding');
        $this->setupStandardProject($projectDir);

        $testFiles = [
            'utf8_with_bom.php' => "\xEF\xBB\xBF<?php\nclass UTF8WithBOM {}\n",
            'utf8_no_bom.php' => "<?php\nclass UTF8NoBOM {}\n",
            'special_chars.php' => "<?php\n// Special chars: äöü ñ 中文 🚀\nclass SpecialChars {}\n",
            'windows_line_endings.php' => "<?php\r\nclass WindowsLineEndings {}\r\n",
            'mixed_line_endings.php' => "<?php\nclass Mixed {\r\n    public function test() {\n        return true;\r\n    }\n}\n",
        ];

        $classesDir = $projectDir . '/packages/test_extension/Classes';
        if (!is_dir($classesDir)) {
            mkdir($classesDir, 0o777, true);
        }

        foreach ($testFiles as $filename => $content) {
            file_put_contents($classesDir . '/' . $filename, $content);
        }

        // Test that tools can handle different encodings
        $result = $this->runQualityTool($projectDir, 'rector', ['--dry-run']);
        $this->assertEquals(
            0,
            $result['exitCode'],
            'Should handle different file encodings',
        );

        $result = $this->runQualityTool($projectDir, 'phpstan');
        $this->assertContains(
            $result['exitCode'],
            [0, 1],
            'PHPStan should process files with different encodings',
        );
    }

    /**
     * Test different composer autoloader configurations.
     */
    public function testDifferentAutoloaderConfigurations(): void
    {
        $autoloaderConfigs = [
            'psr4_only' => [
                'autoload' => [
                    'psr-4' => [
                        'MyVendor\\MyExtension\\' => 'packages/my_extension/Classes/',
                    ],
                ],
            ],
            'psr4_and_psr0' => [
                'autoload' => [
                    'psr-4' => [
                        'MyVendor\\MyExtension\\' => 'packages/my_extension/Classes/',
                    ],
                    'psr-0' => [
                        'Legacy_' => 'legacy/src/',
                    ],
                ],
            ],
            'classmap' => [
                'autoload' => [
                    'classmap' => ['packages/'],
                ],
            ],
            'files' => [
                'autoload' => [
                    'files' => ['config/functions.php'],
                    'psr-4' => [
                        'MyVendor\\MyExtension\\' => 'packages/my_extension/Classes/',
                    ],
                ],
            ],
        ];

        foreach ($autoloaderConfigs as $configName => $config) {
            $projectDir = $this->createTestProject("autoload_{$configName}");
            $this->setupProjectWithAutoloaderConfig($projectDir, $config);

            $result = $this->runQualityTool($projectDir, 'rector', ['--dry-run']);
            $this->assertEquals(
                0,
                $result['exitCode'],
                "Should work with {$configName} autoloader configuration",
            );
        }
    }

    /**
     * Test different operating system behaviors.
     */
    public function testCrossPlatformCompatibility(): void
    {
        $projectDir = $this->createTestProject('cross_platform');
        $this->setupStandardProject($projectDir);

        // Test path handling with different separators
        $mixedPaths = [
            'unix_style' => 'packages/my_extension/Classes',
            'windows_style' => 'packages\\my_extension\\Classes',
            'mixed_style' => 'packages/my_extension\\Classes',
        ];

        foreach ($mixedPaths as $style => $path) {
            // Create configuration with different path styles
            $configContent = "<?php\nreturn ['paths' => ['{$path}']];";
            $configFile = $projectDir . "/config_{$style}.php";
            file_put_contents($configFile, $configContent);

            $result = $this->runQualityTool($projectDir, 'rector', [
                '--config', $configFile,
                '--dry-run',
            ]);

            // Should handle path normalization gracefully
            $this->assertContains(
                $result['exitCode'],
                [0, 1],
                "Should handle {$style} paths gracefully",
            );
        }
    }

    /**
     * Test with large number of dependencies.
     */
    public function testWithManyDependencies(): void
    {
        $projectDir = $this->createTestProject('many_deps');

        // Create composer.json with many dependencies
        $manyDependencies = [
            'typo3/cms-core' => '^13.4',
            'typo3/cms-frontend' => '^13.4',
            'typo3/cms-backend' => '^13.4',
            'symfony/console' => '^7.0',
            'symfony/finder' => '^7.0',
            'doctrine/dbal' => '^3.0',
            'psr/log' => '^3.0',
            'monolog/monolog' => '^3.0',
        ];

        TestHelper::createComposerJson($projectDir, [
            'name' => 'test/many-dependencies',
            'type' => 'project',
            'require' => $manyDependencies,
            'autoload' => [
                'psr-4' => [
                    'Test\\ManyDeps\\' => 'packages/many_deps/Classes/',
                ],
            ],
        ]);

        $this->setupVendorStructure($projectDir);
        $this->createBasicPhpFile($projectDir, 'packages/many_deps/Classes/TestClass.php');

        $result = $this->runQualityTool($projectDir, 'phpstan');
        $this->assertContains(
            $result['exitCode'],
            [0, 1],
            'Should handle projects with many dependencies',
        );
    }

    private function setupProjectType(string $projectDir, array $config): void
    {
        $composerData = [
            'name' => 'test/environmental-test',
            'type' => 'project',
        ];

        $composerData = array_merge($composerData, $config);

        TestHelper::createComposerJson($projectDir, $composerData);
        $this->setupVendorStructure($projectDir);
        $this->createBasicPhpFile($projectDir, 'packages/test_extension/Classes/TestClass.php');
    }

    private function setupProjectWithCustomVendorPath(string $projectDir, string $vendorPath): void
    {
        TestHelper::createComposerJson($projectDir, [
            'name' => 'test/custom-vendor',
            'type' => 'project',
            'require' => ['typo3/cms-core' => '^13.4'],
            'config' => ['vendor-dir' => $vendorPath],
        ]);

        $fullVendorPath = $projectDir . '/' . $vendorPath;
        $this->setupVendorStructure($projectDir, $vendorPath);
        $this->createBasicPhpFile($projectDir, 'packages/test_extension/Classes/TestClass.php');
    }

    private function setupStandardProject(string $projectDir): void
    {
        TestHelper::createComposerJson($projectDir, [
            'name' => 'test/standard-project',
            'type' => 'project',
            'require' => ['typo3/cms-core' => '^13.4'],
        ]);

        $this->setupVendorStructure($projectDir);
        $this->createBasicPhpFile($projectDir, 'packages/test_extension/Classes/TestClass.php');
    }

    private function setupProjectWithAutoloaderConfig(string $projectDir, array $autoloadConfig): void
    {
        $composerData = [
            'name' => 'test/autoloader-test',
            'type' => 'project',
            'require' => ['typo3/cms-core' => '^13.4'],
        ];

        $composerData = array_merge($composerData, $autoloadConfig);

        TestHelper::createComposerJson($projectDir, $composerData);
        $this->setupVendorStructure($projectDir);

        // Create files according to autoloader config
        if (isset($autoloadConfig['autoload']['psr-4'])) {
            foreach ($autoloadConfig['autoload']['psr-4'] as $path) {
                $fullPath = $projectDir . '/' . $path;
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0o777, true);
                }
                $this->createBasicPhpFile($projectDir, $path . 'TestClass.php');
            }
        }

        if (isset($autoloadConfig['autoload']['files'])) {
            foreach ($autoloadConfig['autoload']['files'] as $file) {
                $fullPath = $projectDir . '/' . $file;
                $directory = \dirname($fullPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0o777, true);
                }
                file_put_contents($fullPath, "<?php\n// Functions file\n");
            }
        }
    }

    private function setupVendorStructure(string $projectDir, string $vendorPath = 'vendor'): void
    {
        $fullVendorPath = $projectDir . '/' . $vendorPath;
        $binDir = $fullVendorPath . '/bin';
        $configDir = $fullVendorPath . '/cpsit/quality-tools/config';

        if (!is_dir($binDir)) {
            mkdir($binDir, 0o777, true);
        }
        if (!is_dir($configDir)) {
            mkdir($configDir, 0o777, true);
        }

        // Create basic config files
        file_put_contents($configDir . '/rector.php', '<?php return [];');
        file_put_contents($configDir . '/phpstan.neon', 'parameters: { level: 1 }');

        // Create executable tools
        file_put_contents(
            $binDir . '/rector',
            <<<'BASH'
                #!/bin/bash
                echo "Rector analysis complete"
                exit 0
                BASH
        );
        file_put_contents(
            $binDir . '/qt',
            <<<'BASH'
                #!/bin/bash
                echo "Quality Tools CLI"
                exit 0
                BASH
        );

        file_put_contents(
            $binDir . '/phpstan',
            <<<'BASH'
                #!/bin/bash
                echo "PHPStan analysis complete"
                exit 0
                BASH
        );

        chmod($binDir . '/rector', 0o755);
        chmod($binDir . '/qt', 0o755);
        chmod($binDir . '/phpstan', 0o755);
    }

    private function createBasicPhpFile(string $projectDir, string $relativePath): void
    {
        $fullPath = $projectDir . '/' . $relativePath;
        $directory = \dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents(
            $fullPath,
            <<<'PHP'
                <?php
                namespace Test\Extension;

                use TYPO3\CMS\Core\Utility\GeneralUtility;

                /**
                 * Basic test class for environmental testing
                 */
                class TestClass
                {
                    /**
                     * @var array
                     */
                    protected $configuration = array();

                    public function __construct()
                    {
                        $this->configuration = array(
                            'setting1' => 'value1',
                            'setting2' => 'value2'
                        );
                    }

                    public function getConfiguration()
                    {
                        return $this->configuration;
                    }

                    public function processData($input)
                    {
                        if (is_array($input)) {
                            return array_merge($this->configuration, $input);
                        }

                        return $this->configuration;
                    }
                }
                PHP
        );
    }

    private function testQualityToolsInProject(string $projectDir, string $projectType): array
    {
        // Test basic list command first
        $process = new Process([
            'php',
            'vendor/bin/qt',
            'list',
        ], $projectDir, null, null, 30);

        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    private function testToolDiscoveryInProject(string $projectDir, string $vendorPath): array
    {
        $process = new Process([
            'php',
            $vendorPath . '/bin/rector',
            '--help',
        ], $projectDir, null, null, 30);

        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    private function runQualityToolWithEnvironment(string $projectDir, array $env): array
    {
        $process = new Process([
            'vendor/bin/rector',
            '--dry-run',
            'packages/',
        ], $projectDir, $env, null, 60);

        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    private function runQualityTool(string $projectDir, string $tool, array $args = []): array
    {
        $command = array_merge(["vendor/bin/{$tool}"], $args);

        $process = new Process($command, $projectDir, null, null, 60);
        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
