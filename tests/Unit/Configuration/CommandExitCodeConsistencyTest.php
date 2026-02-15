<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Configuration;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationLoaderWrapper;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Configuration\HierarchicalConfigurationLoader;
use Cpsit\QualityTools\Configuration\SimpleConfigurationLoader;
use Cpsit\QualityTools\Console\Command\ComposerFixCommand;
use Cpsit\QualityTools\Console\Command\ComposerLintCommand;
use Cpsit\QualityTools\Console\Command\PhpCsFixerFixCommand;
use Cpsit\QualityTools\Console\Command\PhpCsFixerLintCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests command exit code consistency between wrapper and unified configuration loaders.
 *
 * Problem: Different error handling causes exit code mismatches (1 vs 0).
 */
final class CommandExitCodeConsistencyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('command_exit_code_test_');
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));
        TestHelper::createVendorStructure($this->tempDir, false, true); // Include config files for tool testing
        $this->createMockExecutables();
        $this->createTestConfiguration();
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    private function createMockExecutables(): void
    {
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        if (!is_dir($vendorBinDir)) {
            mkdir($vendorBinDir, 0o777, true);
        }

        // Mock composer executable that always succeeds
        $composerScript = "#!/bin/bash\necho 'composer.json has been normalized.'\nexit 0\n";
        $composerExecutable = $vendorBinDir . '/composer';
        file_put_contents($composerExecutable, $composerScript);
        chmod($composerExecutable, 0o755);

        // Mock php-cs-fixer executable that always succeeds
        $phpCsFixerScript = "#!/bin/bash\necho 'Files processed successfully.'\nexit 0\n";
        $phpCsFixerExecutable = $vendorBinDir . '/php-cs-fixer';
        file_put_contents($phpCsFixerExecutable, $phpCsFixerScript);
        chmod($phpCsFixerExecutable, 0o755);

        // Create dummy composer.json to fix
        file_put_contents($this->tempDir . '/composer.json', '{}');

        // Create dummy PHP file to fix
        mkdir($this->tempDir . '/src', 0o777, true);
        file_put_contents($this->tempDir . '/src/Test.php', "<?php\nclass Test\n{\n}\n");
    }

    private function createTestConfiguration(): void
    {
        $config = [
            'quality-tools' => [
                'project' => [
                    'name' => 'exit-code-test',
                    'php_version' => '8.3',
                ],
                'tools' => [
                    'php-cs-fixer' => ['enabled' => true],
                    'rector' => ['enabled' => true],
                ],
            ],
        ];

        file_put_contents(
            $this->tempDir . '/quality-tools.yaml',
            Yaml::dump($config, 4, 2)
        );
    }

    #[DataProvider('commandProvider')]
    public function testCommandBehaviorParity(string $commandClass): void
    {
        // Create wrapper-based configuration loader
        $wrapperLoader = $this->createWrapperLoader();

        // Create unified configuration loader
        $unifiedLoader = $this->createUnifiedLoader();

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($commandClass, $wrapperLoader, $unifiedLoader): void {
                $app = new QualityToolsApplication();

                // Test wrapper-based command
                $wrapperCommand = new $commandClass(configurationLoader: $wrapperLoader);
                $wrapperCommand->setApplication($app);
                $wrapperTester = new CommandTester($wrapperCommand);
                $wrapperExitCode = $wrapperTester->execute([]);

                // Test unified command
                $unifiedCommand = new $commandClass(configurationLoader: $unifiedLoader);
                $unifiedCommand->setApplication($app);
                $unifiedTester = new CommandTester($unifiedCommand);
                $unifiedExitCode = $unifiedTester->execute([]);

                // Exit codes must match
                $this->assertSame(
                    $wrapperExitCode,
                    $unifiedExitCode,
                    sprintf(
                        "Exit codes should match for %s. Wrapper: %d, Unified: %d\nWrapper output: %s\nUnified output: %s",
                        $commandClass,
                        $wrapperExitCode,
                        $unifiedExitCode,
                        $wrapperTester->getDisplay(),
                        $unifiedTester->getDisplay()
                    )
                );

                // Both should succeed (exit code 0) for successful operations
                $this->assertEquals(0, $wrapperExitCode, "Wrapper command should succeed");
                $this->assertEquals(0, $unifiedExitCode, "Unified command should succeed");
            }
        );
    }

    public function testCommandErrorHandlingConsistency(): void
    {
        // Create scenario that might cause errors
        $this->createErrorScenario();

        $wrapperLoader = $this->createWrapperLoader();
        $unifiedLoader = $this->createUnifiedLoader();

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use ($wrapperLoader, $unifiedLoader): void {
                $app = new QualityToolsApplication();

                // Test both commands with error scenario
                $wrapperCommand = new ComposerFixCommand(configurationLoader: $wrapperLoader);
                $wrapperCommand->setApplication($app);
                $wrapperTester = new CommandTester($wrapperCommand);
                $wrapperExitCode = $wrapperTester->execute([]);

                $unifiedCommand = new ComposerFixCommand(configurationLoader: $unifiedLoader);
                $unifiedCommand->setApplication($app);
                $unifiedTester = new CommandTester($unifiedCommand);
                $unifiedExitCode = $unifiedTester->execute([]);

                // Error handling should be consistent
                $this->assertSame(
                    $wrapperExitCode,
                    $unifiedExitCode,
                    "Error handling should produce same exit codes"
                );
            }
        );
    }

    public function testCommandWithHierarchicalConfiguration(): void
    {
        // Create hierarchical configuration scenario
        $this->createHierarchicalScenario();

        $wrapperLoader = $this->createWrapperLoader('hierarchical');
        $unifiedLoader = $this->createUnifiedLoader();

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir . '/child'],
            function () use ($wrapperLoader, $unifiedLoader): void {
                $app = new QualityToolsApplication();
                $app->clearCachedProjectRoot(); // Force re-detection with environment variable

                // Test commands with hierarchical configuration
                $wrapperCommand = new ComposerFixCommand(configurationLoader: $wrapperLoader);
                $wrapperCommand->setApplication($app);
                $wrapperTester = new CommandTester($wrapperCommand);
                $wrapperExitCode = $wrapperTester->execute([]);

                $unifiedCommand = new ComposerFixCommand(configurationLoader: $unifiedLoader);
                $unifiedCommand->setApplication($app);
                $unifiedTester = new CommandTester($unifiedCommand);
                $unifiedExitCode = $unifiedTester->execute([]);

                // Hierarchical configuration should produce same results
                $this->assertSame(
                    $wrapperExitCode,
                    $unifiedExitCode,
                    "Hierarchical configuration should produce same exit codes"
                );
            }
        );
    }

    public static function commandProvider(): array
    {
        return [
            'ComposerFixCommand' => [ComposerFixCommand::class],
            'ComposerLintCommand' => [ComposerLintCommand::class],
            'PhpCsFixerFixCommand' => [PhpCsFixerFixCommand::class],
            'PhpCsFixerLintCommand' => [PhpCsFixerLintCommand::class],
        ];
    }

    private function createWrapperLoader(string $mode = 'simple'): ConfigurationLoaderWrapper
    {
        $simpleLoader = new SimpleConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new \Symfony\Component\Filesystem\Filesystem())
        );

        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService()
        );

        return new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            $mode
        );
    }

    private function createUnifiedLoader(): ConfigurationLoader
    {
        return new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService()
        );
    }

    private function createErrorScenario(): void
    {
        // Create invalid composer.json that might cause errors
        file_put_contents($this->tempDir . '/composer.json', '{invalid json}');

        // Remove executable permissions from mock tools
        $vendorBinDir = $this->tempDir . '/vendor/bin';
        if (file_exists($vendorBinDir . '/composer')) {
            chmod($vendorBinDir . '/composer', 0o644);
        }
    }

    private function createHierarchicalScenario(): void
    {
        $parentDir = $this->tempDir . '/parent';
        $childDir = $parentDir . '/child';
        mkdir($childDir, 0o777, true);

        // Parent configuration
        $parentConfig = [
            'quality-tools' => [
                'project' => ['name' => 'parent-project'],
                'tools' => ['rector' => ['enabled' => true]],
            ],
        ];

        // Child configuration
        $childConfig = [
            'quality-tools' => [
                'project' => ['name' => 'child-project'],
                'tools' => ['php-cs-fixer' => ['enabled' => true]],
            ],
        ];

        file_put_contents($parentDir . '/quality-tools.yaml', Yaml::dump($parentConfig, 4, 2));
        file_put_contents($childDir . '/quality-tools.yaml', Yaml::dump($childConfig, 4, 2));

        // Create child composer.json
        file_put_contents($childDir . '/composer.json', '{}');

        // Create child vendor structure with config files
        TestHelper::createVendorStructure($childDir, false, true);
        $childVendorBinDir = $childDir . '/vendor/bin';
        if (!is_dir($childVendorBinDir)) {
            mkdir($childVendorBinDir, 0o777, true);
        }

        // Copy executables to child
        $composerScript = "#!/bin/bash\necho 'composer.json has been normalized.'\nexit 0\n";
        file_put_contents($childVendorBinDir . '/composer', $composerScript);
        chmod($childVendorBinDir . '/composer', 0o755);
    }
}
