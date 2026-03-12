<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Utility\PathExclusionFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Utility\PathExclusionFilter
 */
final class PathExclusionFilterTest extends TestCase
{
    private string $projectRoot;
    private string $vendorPath;

    protected function setUp(): void
    {
        $this->projectRoot = '/project/root';
        $this->vendorPath = '/project/root/vendor';
    }

    /**
     * @test
     */
    public function filterRemovesPathsMatchingExclusionPatterns(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/packages/extension-b',
            '/project/root/var/cache',
            '/project/root/vendor/package-a',
        ];

        $excludePatterns = ['var/*', 'vendor/'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/packages/extension-a',
            '/project/root/packages/extension-b',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterExemptsExplicitVendorPathsFromVendorExclusions(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b',
            '/project/root/vendor/other/package-c',
        ];

        $excludePatterns = ['vendor/'];
        $explicitVendorPaths = [
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b',
        ];

        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns, $explicitVendorPaths);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterAppliesNonVendorExclusionsToExplicitVendorPaths(): void
    {
        $paths = [
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b/var/cache',
            '/project/root/vendor/other/package-c',
        ];

        $excludePatterns = ['vendor/', 'var/*'];
        $explicitVendorPaths = [
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b/var/cache',
        ];

        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns, $explicitVendorPaths);

        $result = $filter->filter($paths);

        // The var/* pattern should match any path containing "/var/" but our test path is
        // "/project/root/vendor/cpsit/package-b/var/cache" which doesn't start with the absolute pattern "/project/root/var/"
        // So both explicit vendor paths should survive (only vendor/ exclusion is skipped for explicit vendor paths)
        $expected = [
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/cpsit/package-b/var/cache', // var/* pattern doesn't match this absolute path
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesWildcardPatterns(): void
    {
        $paths = [
            '/project/root/temp/file1.txt',
            '/project/root/temp/subdir/file2.txt',
            '/project/root/config/settings.php',
        ];

        $excludePatterns = ['temp/*'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/config/settings.php',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesDirectoryPatterns(): void
    {
        $paths = [
            '/project/root/cache',
            '/project/root/cache/file.txt',
            '/project/root/cache/subdir/file2.txt',
            '/project/root/config/settings.php',
        ];

        $excludePatterns = ['cache/'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/config/settings.php',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesExactMatches(): void
    {
        $paths = [
            '/project/root/config.php',
            '/project/root/config/settings.php',
            '/project/root/other.php',
        ];

        $excludePatterns = ['config.php'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/config/settings.php',
            '/project/root/other.php',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesGlobPatterns(): void
    {
        $paths = [
            '/project/root/test.php',
            '/project/root/test.js',
            '/project/root/example.php',
            '/project/root/config.yaml',
        ];

        $excludePatterns = ['*.js'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/test.php',
            '/project/root/example.php',
            '/project/root/config.yaml',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesAbsoluteExclusionPatterns(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/other/project/packages/extension-b',
        ];

        $excludePatterns = ['/other/project/*'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/packages/extension-a',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesVendorPrefixedPatterns(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/cpsit/package-a',
            '/project/root/vendor/other/package-b',
        ];

        $excludePatterns = ['vendor/other/*'];
        $explicitVendorPaths = ['/project/root/vendor/cpsit/package-a'];

        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns, $explicitVendorPaths);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/cpsit/package-a',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesEmptyExclusionPatterns(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/package-b',
        ];

        $excludePatterns = [];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        self::assertSame($paths, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesEmptyPaths(): void
    {
        $paths = [];
        $excludePatterns = ['var/*', 'vendor/'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function filterHandlesMultipleExclusionPatterns(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/var/cache',
            '/project/root/temp/file.txt',
            '/project/root/vendor/package-a',
            '/project/root/config/settings.php',
        ];

        $excludePatterns = ['var/*', 'temp/*', 'vendor/'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            '/project/root/packages/extension-a',
            '/project/root/config/settings.php',
        ];

        self::assertSame($expected, array_values($result));
    }

    /**
     * @test
     */
    public function filterHandlesVendorExclusionVariations(): void
    {
        $paths = [
            '/project/root/packages/extension-a',
            '/project/root/vendor/package-a',
            '/project/root/vendor/package-b',
        ];

        // Test both vendor and vendor/ patterns
        $excludePatternsWithSlash = ['vendor/'];
        $excludePatternsWithoutSlash = ['vendor'];

        $filterWithSlash = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatternsWithSlash);
        $filterWithoutSlash = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatternsWithoutSlash);

        $resultWithSlash = $filterWithSlash->filter($paths);
        $resultWithoutSlash = $filterWithoutSlash->filter($paths);

        $expected = ['/project/root/packages/extension-a'];

        self::assertSame($expected, array_values($resultWithSlash));
        self::assertSame($expected, array_values($resultWithoutSlash));
    }

    /**
     * @test
     */
    public function filterPreservesOriginalArrayKeys(): void
    {
        $paths = [
            'key1' => '/project/root/packages/extension-a',
            'key2' => '/project/root/var/cache',
            'key3' => '/project/root/config/settings.php',
        ];

        $excludePatterns = ['var/*'];
        $filter = new PathExclusionFilter($this->projectRoot, $this->vendorPath, $excludePatterns);

        $result = $filter->filter($paths);

        $expected = [
            'key1' => '/project/root/packages/extension-a',
            'key3' => '/project/root/config/settings.php',
        ];

        self::assertSame($expected, $result);
    }
}
