<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoader;
use Cpsit\QualityTools\Configuration\ConfigurationValidator;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\ProcessExecutor;
use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Tool\Runner\ComposerNormalizeRunner;
use Cpsit\QualityTools\Tool\Runner\DTO\ToolRunRequest;
use Cpsit\QualityTools\Tool\Runner\FractorRunner;
use Cpsit\QualityTools\Tool\Runner\PhpCsFixerRunner;
use Cpsit\QualityTools\Tool\Runner\PhpStanRunner;
use Cpsit\QualityTools\Tool\Runner\RectorRunner;
use Cpsit\QualityTools\Tool\Runner\TypoScriptLintRunner;
use Cpsit\QualityTools\Tool\ToolName;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration test for path configuration across all tool runners.
 *
 * Verifies that each tool runner correctly resolves paths from configuration
 * using the full chain: fixture YAML -> ConfigurationLoader -> Runner::describe().
 * Tests global path overrides, tool-specific overrides, and vendor namespace patterns.
 *
 * @covers \Cpsit\QualityTools\Tool\Runner\RectorRunner
 * @covers \Cpsit\QualityTools\Tool\Runner\PhpStanRunner
 * @covers \Cpsit\QualityTools\Tool\Runner\PhpCsFixerRunner
 * @covers \Cpsit\QualityTools\Tool\Runner\FractorRunner
 * @covers \Cpsit\QualityTools\Tool\Runner\TypoScriptLintRunner
 * @covers \Cpsit\QualityTools\Tool\Runner\ComposerNormalizeRunner
 */
final class ToolCommandPathConfigurationTest extends TestCase
{
    private const string FIXTURES_PATH = __DIR__ . '/../../../Fixtures/021-path-configuration';

    private string $tempDir;
    private ConfigurationLoader $configLoader;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('path_config_test_');

        $this->configLoader = new ConfigurationLoader(
            new ConfigurationValidator(),
            new SecurityService(),
            new FilesystemService(new Filesystem(), new SecurityService()),
            new ToolConfigurationValidationService(),
        );
    }

    protected function tearDown(): void
    {
        putenv('QT_PROJECT_ROOT');
        TestHelper::removeDirectory($this->tempDir);
        VendorDirectoryDetector::clearCache();
    }

    // -- Global path override tests ------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function allToolsProvider(): array
    {
        return [
            'rector' => [ToolName::Rector->value],
            'phpstan' => [ToolName::PhpStan->value],
            'php-cs-fixer' => [ToolName::PhpCsFixer->value],
            'fractor' => [ToolName::Fractor->value],
            'typoscript-lint' => [ToolName::TypoScriptLint->value],
        ];
    }

    #[Test]
    #[DataProvider('allToolsProvider')]
    public function globalPathOverridesAreResolvedForAllTools(string $toolName): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');

        $config = $this->configLoader->load($projectRoot);
        $resolvedPaths = $config->getResolvedPathsForTool($toolName);

        self::assertNotEmpty($resolvedPaths, "Tool '{$toolName}' should have resolved paths from global overrides");

        $pathsAsString = implode(', ', $resolvedPaths);
        $hasSrc = false;
        $hasLib = false;

        foreach ($resolvedPaths as $path) {
            if (str_contains((string) $path, '/src')) {
                $hasSrc = true;
            }
            if (str_contains((string) $path, '/lib')) {
                $hasLib = true;
            }
        }

        self::assertTrue($hasSrc, "Tool '{$toolName}' should resolve 'src/' path. Got: {$pathsAsString}");
        self::assertTrue($hasLib, "Tool '{$toolName}' should resolve 'lib/' path. Got: {$pathsAsString}");
    }

    // -- Tool-specific override tests ----------------------------------------

    #[Test]
    public function rectorToolSpecificPathsReturnFlatList(): void
    {
        $projectRoot = $this->prepareFixture('tool-specific-overrides');

        $config = $this->configLoader->load($projectRoot);
        $resolvedPaths = $config->getResolvedPathsForTool(ToolName::Rector->value);

        self::assertNotEmpty($resolvedPaths, 'Tool-specific paths should be returned');
        $this->assertFlatStringList($resolvedPaths, 'Rector resolved paths');
        self::assertContains(
            'custom-rector-src/',
            $resolvedPaths,
            'Flat list should contain the custom rector path',
        );
    }

    #[Test]
    public function phpstanToolSpecificPathsReturnFlatList(): void
    {
        $projectRoot = $this->prepareFixture('tool-specific-overrides');

        $config = $this->configLoader->load($projectRoot);
        $resolvedPaths = $config->getResolvedPathsForTool(ToolName::PhpStan->value);

        self::assertNotEmpty($resolvedPaths, 'Tool-specific paths should be returned');
        $this->assertFlatStringList($resolvedPaths, 'PHPStan resolved paths');
        self::assertContains(
            'custom-phpstan-src/',
            $resolvedPaths,
            'Flat list should contain the custom phpstan path',
        );
    }

    #[Test]
    public function toolWithoutSpecificOverrideFallsBackToGlobalPaths(): void
    {
        $projectRoot = $this->prepareFixture('tool-specific-overrides');

        $config = $this->configLoader->load($projectRoot);
        $resolvedPaths = $config->getResolvedPathsForTool(ToolName::PhpCsFixer->value);

        $hasSrc = false;
        foreach ($resolvedPaths as $path) {
            if (str_contains((string) $path, '/src')) {
                $hasSrc = true;
            }
        }

        self::assertTrue(
            $hasSrc,
            'PhpCsFixer (no tool-specific override) should fall back to global path "src/". Got: ' . implode(', ', $resolvedPaths),
        );
    }

    // -- Vendor namespace pattern tests --------------------------------------

    #[Test]
    public function vendorNamespacePatternsExpandCorrectly(): void
    {
        $projectRoot = $this->prepareFixture('vendor-namespace-patterns');

        $config = $this->configLoader->load($projectRoot);
        $resolvedPaths = $config->getResolvedPathsForTool(ToolName::Rector->value);

        $hasPackages = false;
        $vendorPackageCount = 0;

        foreach ($resolvedPaths as $path) {
            if (str_contains((string) $path, '/packages')) {
                $hasPackages = true;
            }
            if (str_contains((string) $path, '/vendor/company/')) {
                ++$vendorPackageCount;
            }
        }

        self::assertTrue($hasPackages, 'Should include packages/ path. Got: ' . implode(', ', $resolvedPaths));
        self::assertSame(
            2,
            $vendorPackageCount,
            'vendor/company/* should expand to 2 packages (pkg1, pkg2). Got: ' . implode(', ', $resolvedPaths),
        );
    }

    #[Test]
    public function vendorNamespacePatternsWorkConsistentlyAcrossTools(): void
    {
        $projectRoot = $this->prepareFixture('vendor-namespace-patterns');

        $config = $this->configLoader->load($projectRoot);

        $toolNames = [
            ToolName::Rector->value,
            ToolName::PhpStan->value,
            ToolName::PhpCsFixer->value,
            ToolName::Fractor->value,
        ];

        $pathCountByTool = [];
        foreach ($toolNames as $toolName) {
            $resolved = $config->getResolvedPathsForTool($toolName);
            $pathCountByTool[$toolName] = \count($resolved);

            $vendorPaths = array_filter(
                $resolved,
                static fn (string $path): bool => str_contains($path, '/vendor/company/'),
            );

            self::assertGreaterThanOrEqual(
                2,
                \count($vendorPaths),
                "Tool '{$toolName}' should resolve at least 2 vendor packages from pattern. Got: " . implode(', ', $resolved),
            );
        }
    }

    // -- Runner describe() integration tests ---------------------------------

    #[Test]
    public function rectorRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createRectorRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::Rector->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::Rector->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'Rector describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'Rector target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'Rector target paths');
    }

    #[Test]
    public function phpstanRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createPhpStanRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::PhpStan->value,
            dryRun: false,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::PhpStan->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'PHPStan describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'PHPStan target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'PHPStan target paths');
    }

    #[Test]
    public function phpCsFixerRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createPhpCsFixerRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::PhpCsFixer->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::PhpCsFixer->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'PhpCsFixer describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'PhpCsFixer target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'PhpCsFixer target paths');
    }

    #[Test]
    public function fractorRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createFractorRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::Fractor->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::Fractor->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'Fractor describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'Fractor target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'Fractor target paths');
    }

    #[Test]
    public function typoscriptLintRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createTypoScriptLintRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::TypoScriptLint->value,
            dryRun: false,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::TypoScriptLint->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'TypoScriptLint describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'TypoScriptLint target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'TypoScriptLint target paths');
    }

    #[Test]
    public function composerNormalizeRunnerDescribeResolvesPathsFromConfiguration(): void
    {
        $projectRoot = $this->prepareFixture('global-path-overrides');
        $runner = $this->createComposerNormalizeRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::ComposerNormalize->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertSame(ToolName::ComposerNormalize->value, $description->toolName);
        self::assertNotEmpty($description->targetPaths, 'ComposerNormalize describe() should resolve target paths from config');

        $this->assertPathsContainSubstring($description->targetPaths, '/src', 'ComposerNormalize target paths');
        $this->assertPathsContainSubstring($description->targetPaths, '/lib', 'ComposerNormalize target paths');
    }

    // -- Runner with tool-specific overrides ---------------------------------

    #[Test]
    public function rectorRunnerDescribeReturnsNonEmptyPathsWithToolSpecificConfig(): void
    {
        $projectRoot = $this->prepareFixture('tool-specific-overrides');
        $runner = $this->createRectorRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::Rector->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertNotEmpty(
            $description->targetPaths,
            'Rector describe() should return non-empty paths with tool-specific config',
        );
        $this->assertFlatStringList($description->targetPaths, 'Rector targetPaths');
        self::assertContains(
            'custom-rector-src/',
            $description->targetPaths,
            'Rector targetPaths should contain the flat path string, not nested arrays',
        );
    }

    #[Test]
    public function phpstanRunnerDescribeReturnsNonEmptyPathsWithToolSpecificConfig(): void
    {
        $projectRoot = $this->prepareFixture('tool-specific-overrides');
        $runner = $this->createPhpStanRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::PhpStan->value,
            dryRun: false,
        );

        $description = $runner->describe($request);

        self::assertNotEmpty(
            $description->targetPaths,
            'PHPStan describe() should return non-empty paths with tool-specific config',
        );
        $this->assertFlatStringList($description->targetPaths, 'PHPStan targetPaths');
        self::assertContains(
            'custom-phpstan-src/',
            $description->targetPaths,
            'PHPStan targetPaths should contain the flat path string, not nested arrays',
        );
    }

    // -- Runner with vendor namespace patterns -------------------------------

    #[Test]
    public function rectorRunnerDescribeExpandsVendorNamespacePatterns(): void
    {
        $projectRoot = $this->prepareFixture('vendor-namespace-patterns');
        $runner = $this->createRectorRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::Rector->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertGreaterThanOrEqual(
            3,
            \count($description->targetPaths),
            'Rector should resolve packages/ + 2 vendor packages. Got: ' . implode(', ', $description->targetPaths),
        );
    }

    // -- Runner config path resolution tests ---------------------------------

    #[Test]
    public function rectorRunnerDescribeResolvesDefaultConfigPath(): void
    {
        $projectRoot = $this->prepareFixture('default-paths');
        $runner = $this->createRectorRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::Rector->value,
            dryRun: true,
        );

        $description = $runner->describe($request);

        self::assertStringContainsString(
            'rector.php',
            $description->configPath,
            'Rector should resolve a config path containing rector.php',
        );
    }

    #[Test]
    public function phpstanRunnerDescribeResolvesDefaultConfigPath(): void
    {
        $projectRoot = $this->prepareFixture('default-paths');
        $runner = $this->createPhpStanRunner($projectRoot);

        $request = new ToolRunRequest(
            toolName: ToolName::PhpStan->value,
            dryRun: false,
        );

        $description = $runner->describe($request);

        self::assertStringContainsString(
            'phpstan.neon',
            $description->configPath,
            'PHPStan should resolve a config path containing phpstan.neon',
        );
    }

    // -- Helpers -------------------------------------------------------------

    /**
     * Copy a fixture to the temp directory and return the resolved path.
     */
    private function prepareFixture(string $fixtureName): string
    {
        $fixturePath = self::FIXTURES_PATH . '/' . $fixtureName;
        self::assertDirectoryExists($fixturePath, "Fixture '{$fixtureName}' not found");

        $this->copyDirectory($fixturePath, $this->tempDir);

        // Create a minimal vendor structure so VendorDirectoryDetector works
        TestHelper::createVendorStructure($this->tempDir, false, true);

        return $this->tempDir;
    }

    private function createProjectEnvironment(string $projectRoot): ProjectEnvironment
    {
        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        // Use environment variable to set project root
        putenv('QT_PROJECT_ROOT=' . $projectRoot);
        $env->clearCachedProjectRoot();

        return $env;
    }

    private function createRectorRunner(string $projectRoot): RectorRunner
    {
        return new RectorRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    private function createPhpStanRunner(string $projectRoot): PhpStanRunner
    {
        return new PhpStanRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    private function createPhpCsFixerRunner(string $projectRoot): PhpCsFixerRunner
    {
        return new PhpCsFixerRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    private function createFractorRunner(string $projectRoot): FractorRunner
    {
        return new FractorRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    private function createTypoScriptLintRunner(string $projectRoot): TypoScriptLintRunner
    {
        return new TypoScriptLintRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    private function createComposerNormalizeRunner(string $projectRoot): ComposerNormalizeRunner
    {
        return new ComposerNormalizeRunner(
            new ProcessExecutor(),
            $this->createProjectEnvironment($projectRoot),
            $this->configLoader,
        );
    }

    /**
     * Assert that the given value is a flat list of strings -- no string keys
     * and no nested array elements. Containment checks alone pass even when the
     * array carries extra nested junk, so this guards the actual contract.
     *
     * @param array<mixed> $paths
     */
    private function assertFlatStringList(array $paths, string $context): void
    {
        self::assertTrue(
            array_is_list($paths),
            "{$context} should be a flat list (sequential integer keys), not a keyed/nested structure.",
        );
        foreach ($paths as $path) {
            self::assertIsString(
                $path,
                "{$context} should contain only string paths, not nested arrays or other types.",
            );
        }
    }

    /**
     * Assert that at least one path in the list contains the given substring.
     *
     * @param list<string> $paths
     */
    private function assertPathsContainSubstring(array $paths, string $substring, string $context): void
    {
        $found = false;
        foreach ($paths as $path) {
            if (str_contains($path, $substring)) {
                $found = true;

                break;
            }
        }

        self::assertTrue(
            $found,
            "{$context} should contain a path with '{$substring}'. Got: " . implode(', ', $paths),
        );
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();

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
