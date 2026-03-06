<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Command;

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
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests command exit code consistency between wrapper and unified configuration loaders.
 *
 * Problem: Different error handling causes exit code mismatches (1 vs 0).
 */
final class CommandExitCodeConsistencyTest extends TestCase
{
    private string $tempDir;
    private FilesystemService $filesystemService;
    private SecurityService $securityService;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('command_exit_code_test_');
        $this->securityService = new SecurityService();
        $this->filesystemService = new FilesystemService(new Filesystem(), $this->securityService);

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

        // Create mock composer script that simulates composer-normalize plugin
        $composerScript = '#!/bin/bash
# Mock composer script that simulates composer-normalize behavior
if [ "$1" = "normalize" ]; then
    shift
    COMPOSER_FILE=""
    for arg in "$@"; do
        case "$arg" in
            --dry-run|--diff) ;;
            *.json) COMPOSER_FILE="$arg" ;;
        esac
    done

    TARGET="${COMPOSER_FILE:-composer.json}"
    if [ -f "$TARGET" ]; then
        php -r "json_decode(file_get_contents(\"$TARGET\")); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);" 2>/dev/null
        if [ $? -ne 0 ]; then
            echo "\"$TARGET\" does not contain valid JSON" >&2
            exit 1
        fi
        echo "Running ergebnis/composer-normalize by Andreas Möller and contributors."
        echo "$TARGET is already normalized."
        exit 0
    fi
    echo "No composer.json found"
    exit 1
else
    echo "Mock composer: unsupported command $*"
    exit 1
fi
';
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
            Yaml::dump($config, 4, 2),
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
                    \sprintf(
                        "Exit codes should match for %s. Wrapper: %d, Unified: %d\nWrapper output: %s\nUnified output: %s",
                        $commandClass,
                        $wrapperExitCode,
                        $unifiedExitCode,
                        $wrapperTester->getDisplay(),
                        $unifiedTester->getDisplay(),
                    ),
                );

                // Both should succeed (exit code 0) for successful operations
                $this->assertEquals(0, $wrapperExitCode, 'Wrapper command should succeed');
                $this->assertEquals(0, $unifiedExitCode, 'Unified command should succeed');
            },
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
                    'Error handling should produce same exit codes',
                );
            },
        );
    }

    #[DataProvider('composerScenarioWithCommandProvider')]
    public function testCommandBehaviorWithComposerScenarios(
        string $commandClass,
        string $scenarioName,
        string $fixtureDirectory,
        int $expectedWrapperExitCode,
        int $expectedUnifiedExitCode,
        string $wrapperExpectedMessage,
        string $unifiedExpectedMessage,
    ): void {
        // Copy fixture directory to temp directory for testing
        $this->copyFixtureToTempDir($fixtureDirectory);

        $wrapperLoader = $this->createWrapperLoader('hierarchical');
        $unifiedLoader = $this->createUnifiedLoader();

        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function () use (
                $commandClass,
                $wrapperLoader,
                $unifiedLoader,
                $expectedWrapperExitCode,
                $expectedUnifiedExitCode,
                $wrapperExpectedMessage,
                $unifiedExpectedMessage,
                $scenarioName
            ): void {
                $app = new QualityToolsApplication();
                $app->clearCachedProjectRoot();

                // Test wrapper command
                $wrapperCommand = new $commandClass(configurationLoader: $wrapperLoader);
                $wrapperCommand->setApplication($app);
                $wrapperTester = new CommandTester($wrapperCommand);
                $wrapperExitCode = $wrapperTester->execute([]);
                $wrapperOutput = $wrapperTester->getDisplay();

                // Test unified command
                $unifiedCommand = new $commandClass(configurationLoader: $unifiedLoader);
                $unifiedCommand->setApplication($app);
                $unifiedTester = new CommandTester($unifiedCommand);
                $unifiedExitCode = $unifiedTester->execute([]);
                $unifiedOutput = $unifiedTester->getDisplay();

                // Debug output for understanding actual behavior
                fwrite(STDERR, "\n=== $scenarioName ===\n");
                fwrite(STDERR, "Wrapper exit code: $wrapperExitCode (expected: $expectedWrapperExitCode)\n");
                fwrite(STDERR, "Unified exit code: $unifiedExitCode (expected: $expectedUnifiedExitCode)\n");
                fwrite(STDERR, "Wrapper output: $wrapperOutput\n");
                fwrite(STDERR, "Unified output: $unifiedOutput\n");

                // Specific assertions for each scenario
                $this->assertEquals(
                    $expectedWrapperExitCode,
                    $wrapperExitCode,
                    "Wrapper command should return exit code $expectedWrapperExitCode for scenario: $scenarioName",
                );

                $this->assertEquals(
                    $expectedUnifiedExitCode,
                    $unifiedExitCode,
                    "Unified command should return exit code $expectedUnifiedExitCode for scenario: $scenarioName",
                );

                // Validate expected output messages
                if (!empty($wrapperExpectedMessage)) {
                    $this->assertStringContainsString(
                        $wrapperExpectedMessage,
                        $wrapperOutput,
                        "Wrapper output should contain expected message for scenario: $scenarioName",
                    );
                }

                if (!empty($unifiedExpectedMessage)) {
                    $this->assertStringContainsString(
                        $unifiedExpectedMessage,
                        $unifiedOutput,
                        "Unified output should contain expected message for scenario: $scenarioName",
                    );
                }
            },
        );
    }

    public static function composerScenarioProvider(): array
    {
        return [
            // Scenario 1: Empty directory - no composer.json files
            'empty_directory' => [
                'scenarioName' => 'Empty Directory',
                'fixtureDirectory' => 'empty-project',
                'expectedWrapperExitCode' => 1, // No files found
                'expectedUnifiedExitCode' => 1, // No files found
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'No composer.json files found',
            ],

            // Scenario 2: Valid composer.json in project root
            'valid_composer_in_root' => [
                'scenarioName' => 'Valid Composer.json in Root',
                'fixtureDirectory' => 'valid-composer-in-root',
                'expectedWrapperExitCode' => 0, // Wrapper finds it with quality-tools.yaml including '.'
                'expectedUnifiedExitCode' => 0, // Unified finds and processes it
                'wrapperExpectedMessage' => 'composer.json has been normalized',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 3: Invalid composer.json in project root
            'invalid_composer_in_root' => [
                'scenarioName' => 'Invalid Composer.json in Root',
                'fixtureDirectory' => 'invalid-composer-in-root',
                'expectedWrapperExitCode' => 1, // Invalid JSON detected by mock composer
                'expectedUnifiedExitCode' => 1, // Invalid JSON detected by mock composer
                'wrapperExpectedMessage' => 'does not contain valid JSON',
                'unifiedExpectedMessage' => 'does not contain valid JSON',
            ],

            // Scenario 4: Custom package in packages/ directory (realistic TYPO3)
            'custom_package_in_packages' => [
                'scenarioName' => 'Custom Package in Packages Directory',
                'fixtureDirectory' => 'custom-package-in-packages',
                'expectedWrapperExitCode' => 1, // Wrapper path resolution issue - doesn't find packages/
                'expectedUnifiedExitCode' => 0, // Unified finds it correctly
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 5: Vendor package in standard vendor/ directory
            'vendor_package' => [
                'scenarioName' => 'Vendor Package in Vendor Directory',
                'fixtureDirectory' => 'vendor-package',
                'expectedWrapperExitCode' => 1, // Wrapper path resolution issue - doesn't find vendor/
                'expectedUnifiedExitCode' => 0, // Unified finds it correctly
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 6: Custom vendor directory structure
            'custom_vendor_dir' => [
                'scenarioName' => 'Custom Vendor Directory Structure',
                'fixtureDirectory' => 'custom-vendor-dir',
                'expectedWrapperExitCode' => 1, // Wrapper path resolution issue - doesn't find custom vendor dir
                'expectedUnifiedExitCode' => 0, // Unified finds it correctly
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 7: Multiple realistic TYPO3 structure
            'multiple_realistic' => [
                'scenarioName' => 'Multiple Realistic TYPO3 Structure',
                'fixtureDirectory' => 'multiple-realistic',
                'expectedWrapperExitCode' => 1, // Wrapper path resolution issue - doesn't find multiple files
                'expectedUnifiedExitCode' => 0, // Unified finds and processes both files
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 8: Project without TYPO3 packages
            'no_typo3_packages' => [
                'scenarioName' => 'Project Without TYPO3 Packages',
                'fixtureDirectory' => 'no-typo3-packages',
                'expectedWrapperExitCode' => 0, // Wrapper finds it with quality-tools.yaml including '.'
                'expectedUnifiedExitCode' => 0, // Unified finds and processes it
                'wrapperExpectedMessage' => 'composer.json has been normalized',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],

            // Scenario 9: Legacy edge case - valid composer.json in packages/ directory (direct)
            'multiple_valid_composers' => [
                'scenarioName' => 'Multiple Valid Composer.json Files (Edge Case)',
                'fixtureDirectory' => 'multiple-valid-composers',
                'expectedWrapperExitCode' => 1, // Wrapper path resolution issue - doesn't find files
                'expectedUnifiedExitCode' => 0, // Unified finds and processes both files
                'wrapperExpectedMessage' => 'No composer.json files found',
                'unifiedExpectedMessage' => 'composer.json has been normalized',
            ],
        ];
    }

    public static function composerScenarioWithCommandProvider(): array
    {
        $commands = [
            'ComposerFixCommand' => ComposerFixCommand::class,
            'ComposerLintCommand' => ComposerLintCommand::class,
        ];

        $scenarios = self::composerScenarioProvider();
        $result = [];

        foreach ($commands as $commandName => $commandClass) {
            foreach ($scenarios as $scenarioKey => $scenario) {
                $key = "{$commandName}__{$scenarioKey}";

                // Adjust expected messages based on command type
                $wrapperMessage = $scenario['wrapperExpectedMessage'];
                $unifiedMessage = $scenario['unifiedExpectedMessage'];

                // Both commands now show real composer output, so we expect consistent messages
                // that reflect the actual state of composer.json files
                if ($unifiedMessage === 'composer.json has been normalized') {
                    // Update to match real composer normalize output
                    $unifiedMessage = 'is already normalized';
                }
                if ($wrapperMessage === 'composer.json has been normalized') {
                    // Update to match real composer normalize output
                    $wrapperMessage = 'is already normalized';
                }

                $result[$key] = [
                    $commandClass, // Command class
                    $scenario['scenarioName'],
                    $scenario['fixtureDirectory'],
                    $scenario['expectedWrapperExitCode'],
                    $scenario['expectedUnifiedExitCode'],
                    $wrapperMessage,
                    $unifiedMessage,
                ];
            }
        }

        return $result;
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
            $this->securityService,
            $this->filesystemService,
        );

        $hierarchicalLoader = new HierarchicalConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
            new ToolConfigurationValidationService(),
        );

        return new ConfigurationLoaderWrapper(
            $simpleLoader,
            $hierarchicalLoader,
            $mode,
        );
    }

    private function createUnifiedLoader(): ConfigurationLoader
    {
        return new ConfigurationLoader(
            new ConfigurationValidator(),
            $this->securityService,
            $this->filesystemService,
            new ToolConfigurationValidationService(),
            new ProjectConfigService(),
            new ToolConfigService(),
            new PathResolutionService($this->filesystemService, new VendorDirectoryDetector()),
        );
    }

    private function copyFixtureToTempDir(string $fixtureDirectory): void
    {
        $fixtureSource = __DIR__ . '/../../Fixtures/composerFixCommand/' . $fixtureDirectory;

        if (!is_dir($fixtureSource)) {
            throw new \RuntimeException("Fixture directory does not exist: {$fixtureSource}");
        }

        // Remove existing composer.json file from setUp() if it exists
        if (file_exists($this->tempDir . '/composer.json')) {
            unlink($this->tempDir . '/composer.json');
        }

        // Copy fixture directory contents to temp directory
        $this->recursiveCopy($fixtureSource, $this->tempDir);
    }

    private function recursiveCopy(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0o755, true);
                }
            } else {
                $destDir = \dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0o755, true);
                }
                copy($item->getRealPath(), $destPath);
            }
        }
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

    /**
     * Legacy hierarchical test - kept for backward compatibility but now uses data provider approach.
     */
    public function testCommandWithHierarchicalConfiguration(): void
    {
        // This test is now covered by the comprehensive data provider test above
        // but kept for backward compatibility with existing test infrastructure
        $this->testCommandBehaviorWithComposerScenarios(
            ComposerFixCommand::class, // Command class
            'Valid Composer.json in Root',
            'valid-composer-in-root',
            0, // Wrapper expected exit code - now finds it with quality-tools.yaml including '.'
            0, // Unified expected exit code
            'is already normalized', // Wrapper expected message - updated to match real composer output
            'is already normalized', // Unified expected message - updated to match real composer output
        );
    }
}
