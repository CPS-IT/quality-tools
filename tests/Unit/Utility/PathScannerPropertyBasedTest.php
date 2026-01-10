<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\PathScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for PathScanner using custom generators and data providers.
 *
 * This implementation provides property-based testing functionality without
 * external dependencies, ensuring compatibility with the current PHPUnit version.
 */
#[CoversClass(PathScanner::class)]
final class PathScannerPropertyBasedTest extends TestCase
{
    private string $tempDir;
    private PathScanner $scanner;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('path_scanner_property_based_test_');
        $this->scanner = new PathScanner($this->tempDir);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    /**
     * Property: Path resolution should be idempotent.
     * Running the same resolution twice should give identical results.
     */
    #[DataProvider('pathPatternsProvider')]
    public function testPathResolutionIdempotency(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        $result1 = $this->scanner->resolvePaths($patterns);
        $result2 = $this->scanner->resolvePaths($patterns);

        $this->assertEquals($result1, $result2, 'Path resolution should be idempotent');
    }

    /**
     * Property: Resolved paths should never contain duplicates.
     */
    #[DataProvider('pathPatternsProvider')]
    public function testNoDuplicatePaths(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        $result = $this->scanner->resolvePaths($patterns);
        $unique = array_unique($result);

        $this->assertEquals($result, $unique, 'Resolved paths should contain no duplicates');
    }

    /**
     * Property: All resolved paths should be absolute and within project bounds.
     */
    #[DataProvider('pathPatternsProvider')]
    public function testPathBounds(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        $result = $this->scanner->resolvePaths($patterns);

        // Get the real path of the temp directory to handle symlink resolution
        $realTempDir = realpath($this->tempDir);

        // Always assert that the result is an array
        $this->assertIsArray($result, 'Result should be an array');

        foreach ($result as $path) {
            $this->assertTrue(
                str_starts_with((string) $path, $realTempDir),
                "Path '$path' should be within project bounds '$realTempDir'",
            );
            $this->assertTrue(
                str_starts_with((string) $path, '/'),
                "Path '$path' should be absolute",
            );
        }
    }

    /**
     * Property: Resolved paths should always exist in the filesystem.
     */
    #[DataProvider('pathPatternsProvider')]
    public function testResolvedPathsExist(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        $result = $this->scanner->resolvePaths($patterns);

        // Always assert that the result is an array
        $this->assertIsArray($result, 'Result should be an array');

        foreach ($result as $path) {
            $this->assertDirectoryExists(
                $path,
                "Resolved path '$path' should exist on filesystem",
            );
        }
    }

    /**
     * Property: Results should be sorted consistently.
     */
    #[DataProvider('pathPatternsProvider')]
    public function testResultsAreSorted(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        $result = $this->scanner->resolvePaths($patterns);
        $sorted = $result;
        sort($sorted);

        $this->assertEquals($sorted, $result, 'Results should be sorted consistently');
    }

    /**
     * Property: Exclusion patterns should reduce or maintain path count.
     */
    #[DataProvider('exclusionPatternsProvider')]
    public function testExclusionReducesOrMaintainsPaths(array $includePatterns, array $excludePatterns): void
    {
        $this->createTestDirectoryStructure();

        $withoutExclusions = $this->scanner->resolvePaths($includePatterns);
        $withExclusions = $this->scanner->resolvePaths(array_merge($includePatterns, $excludePatterns));

        $this->assertLessThanOrEqual(
            \count($withoutExclusions),
            \count($withExclusions),
            'Exclusion patterns should reduce or maintain path count',
        );
    }

    /**
     * Property: Vendor namespace patterns should only work when the vendor path is set.
     */
    #[DataProvider('vendorNamespacePatternsProvider')]
    public function testVendorNamespaceRequiresVendorPath(array $patterns): void
    {
        $this->createVendorDirectoryStructure();

        // Without a vendor path set
        $resultWithoutVendor = $this->scanner->resolvePaths($patterns);

        // With a vendor path set
        $this->scanner->setVendorPath($this->tempDir . '/vendor');
        $resultWithVendor = $this->scanner->resolvePaths($patterns);

        $this->assertGreaterThanOrEqual(
            \count($resultWithoutVendor),
            \count($resultWithVendor),
            'Setting vendor path should enable vendor namespace pattern resolution',
        );
    }

    /**
     * Property: Pattern order should not affect the final result set.
     */
    #[DataProvider('multiplePatternSetsProvider')]
    public function testPatternOrderIndependence(array $patterns1, array $patterns2): void
    {
        $this->createTestDirectoryStructure();

        if (\count($patterns1) < 2) {
            $this->markTestSkipped('Need at least 2 patterns to test ordering');
        }

        $result1 = $this->scanner->resolvePaths($patterns1);
        $result2 = $this->scanner->resolvePaths($patterns2);

        sort($result1);
        sort($result2);

        $this->assertEquals(
            $result1,
            $result2,
            'Pattern order should not affect the final result set',
        );
    }

    /**
     * Property: Malformed patterns should be handled gracefully.
     */
    #[DataProvider('malformedPatternsProvider')]
    public function testMalformedPatternsHandling(array $patterns): void
    {
        $this->createTestDirectoryStructure();

        // Path scanner should not crash on malformed patterns
        try {
            $result = $this->scanner->resolvePaths($patterns);
            $this->assertIsArray($result, 'Result should always be an array even with malformed patterns');

            // All returned paths should still be valid
            foreach ($result as $path) {
                $this->assertIsString($path, 'Each result should be a string');
                $this->assertTrue(
                    str_starts_with($path, $this->tempDir),
                    "Path should be within project bounds: $path",
                );
            }
        } catch (\Throwable $e) {
            // If an exception is thrown, it should be meaningful
            $this->assertStringContainsString(
                'path',
                strtolower($e->getMessage()),
                'Exception message should be related to path processing',
            );
        }
    }

    /**
     * Data provider for various path pattern combinations.
     */
    public static function pathPatternsProvider(): array
    {
        return [
            'empty patterns' => [[]],
            'single directory' => [['src']],
            'multiple directories' => [['src', 'packages']],
            'glob patterns' => [['src/*', 'packages/*']],
            'complex patterns' => [['**/Classes', 'src/**/*.php']],
            'mixed patterns' => [['src', 'packages/*', '**/Classes']],
            'with exclusions' => [['src', 'packages', '!packages/legacy']],
            'complex exclusions' => [['**/Classes', '!vendor/*', '!**/Tests']],
        ];
    }

    /**
     * Data provider for inclusion and exclusion pattern combinations.
     */
    public static function exclusionPatternsProvider(): array
    {
        return [
            'simple exclusion' => [['src', 'packages'], ['!packages/legacy']],
            'glob exclusion' => [['**/Classes'], ['!vendor/*']],
            'multiple exclusions' => [['src', 'packages', 'lib'], ['!**/Tests', '!packages/legacy']],
            'complex exclusions' => [['**/*'], ['!vendor/*', '!**/Tests/*', '!*.backup']],
        ];
    }

    /**
     * Data provider for vendor namespace patterns.
     */
    public static function vendorNamespacePatternsProvider(): array
    {
        return [
            'single namespace' => [['cpsit/*']],
            'multiple namespaces' => [['cpsit/*', 'fr/*']],
            'namespace with path' => [['cpsit/*/Classes']],
            'complex vendor patterns' => [['*/*/Classes', 'cpsit/ext*']],
        ];
    }

    /**
     * Data provider for multiple pattern sets (for order independence testing).
     */
    public static function multiplePatternSetsProvider(): array
    {
        return [
            'same patterns, different order' => [
                ['src', 'packages', 'lib'],
                ['lib', 'src', 'packages'],
            ],
            'glob patterns reordered' => [
                ['src/*', 'packages/*', '**/Classes'],
                ['**/Classes', 'packages/*', 'src/*'],
            ],
            'complex pattern reordering' => [
                ['src', 'packages/*', '**/Classes', '!vendor/*'],
                ['**/Classes', '!vendor/*', 'src', 'packages/*'],
            ],
        ];
    }

    /**
     * Data provider for malformed patterns.
     */
    public static function malformedPatternsProvider(): array
    {
        return [
            'invalid glob patterns' => [['***', '[unclosed', ']{backwards}']],
            'mixed valid/invalid' => [['src', '***', 'packages']],
            'empty strings' => [['', ' ', "\t"]],
            'special characters' => [['src; rm -rf /', '$(echo src)', '`echo src`']],
            'directory traversal' => [['../../../etc', 'src/../../../home']],
            'very long patterns' => [[str_repeat('a', 1000), str_repeat('src/', 100)]],
        ];
    }

    /**
     * Generate additional test cases using a simple random approach.
     * This simulates property-based testing by generating many combinations.
     *
     * @throws \Random\RandomException
     */
    public function testRandomizedPathPatternCombinations(): void
    {
        $this->createExtensiveDirectoryStructure();

        // Generate 50 random pattern combinations
        for ($i = 0; $i < 50; ++$i) {
            $patterns = $this->generateRandomPatterns();

            $result1 = $this->scanner->resolvePaths($patterns);
            $result2 = $this->scanner->resolvePaths($patterns);

            // Test fundamental properties
            $this->assertEquals($result1, $result2, "Iteration $i: Results should be idempotent");
            $this->assertEquals($result1, array_unique($result1), "Iteration $i: No duplicates");

            $realTempDir = realpath($this->tempDir);
            foreach ($result1 as $path) {
                $this->assertTrue(
                    str_starts_with((string) $path, $realTempDir),
                    "Iteration $i: Path $path should be within project bounds $realTempDir",
                );
                $this->assertDirectoryExists(
                    $path,
                    "Iteration $i: Path $path should exist",
                );
            }
        }
    }

    /**
     * Generate random pattern combinations for property testing.
     *
     * @throws \Random\RandomException
     */
    private function generateRandomPatterns(): array
    {
        $basePaths = ['src', 'packages', 'lib', 'app', 'vendor'];
        $globPatterns = ['*', '**/*', '*/Classes', '**/Service*'];
        $exclusions = ['!vendor/*', '!**/Tests', '!packages/legacy'];

        $patterns = [];
        $numPatterns = random_int(1, 5);

        for ($i = 0; $i < $numPatterns; ++$i) {
            $type = random_int(1, 3);

            switch ($type) {
                case 1: // Base path
                    $patterns[] = $basePaths[array_rand($basePaths)];
                    break;
                case 2: // Glob pattern
                    $base = $basePaths[array_rand($basePaths)];
                    $glob = $globPatterns[array_rand($globPatterns)];
                    $patterns[] = $base . '/' . $glob;
                    break;
                case 3: // Exclusion
                    if (random_int(0, 1) !== 0) { // 50% chance to add exclusion
                        $patterns[] = $exclusions[array_rand($exclusions)];
                    }
                    break;
            }
        }

        return $patterns;
    }

    /**
     * Create a test directory structure.
     */
    private function createTestDirectoryStructure(): void
    {
        $directories = [
            'src',
            'src/Classes',
            'src/Classes/Service',
            'src/Tests',
            'packages',
            'packages/ext1',
            'packages/ext1/Classes',
            'packages/ext2',
            'packages/ext2/Classes',
            'packages/ext2/Tests',
            'packages/legacy',
            'packages/legacy/old',
            'lib',
            'lib/util',
            'lib/test',
            'app',
            'app/Classes',
            'app/Service',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->tempDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0o777, true);
            }
        }
    }

    /**
     * Create vendor directory structure.
     */
    private function createVendorDirectoryStructure(): void
    {
        $this->createTestDirectoryStructure();

        $vendorDirectories = [
            'vendor/cpsit/ext1',
            'vendor/cpsit/ext1/Classes',
            'vendor/cpsit/ext2',
            'vendor/cpsit/ext2/Classes',
            'vendor/fr/lib1',
            'vendor/fr/lib1/Classes',
            'vendor/fr/lib2',
            'vendor/fr/lib2/Classes',
            'vendor/symfony/console',
            'vendor/symfony/console/Classes',
        ];

        foreach ($vendorDirectories as $dir) {
            $fullPath = $this->tempDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0o777, true);
            }
        }
    }

    /**
     * Create an extensive directory structure for stress testing.
     */
    private function createExtensiveDirectoryStructure(): void
    {
        $this->createVendorDirectoryStructure();

        // Add many more directories for comprehensive testing
        $baseDirs = ['src', 'packages', 'lib', 'app'];
        $subDirs = ['Classes', 'Service', 'Util', 'Tests', 'Unit', 'Functional'];

        foreach ($baseDirs as $baseDir) {
            foreach ($subDirs as $subDir) {
                $path = $this->tempDir . '/' . $baseDir . '/' . $subDir;
                if (!is_dir($path)) {
                    mkdir($path, 0o777, true);
                }

                // Create sub-subdirectories
                foreach ($subDirs as $subSubDir) {
                    $subPath = $path . '/' . $subSubDir;
                    if (!is_dir($subPath)) {
                        mkdir($subPath, 0o777, true);
                    }
                }
            }
        }
    }
}
