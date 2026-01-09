<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Utility;

use Cpsit\QualityTools\Tests\Unit\TestHelper;
use Cpsit\QualityTools\Utility\PathScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathScanner::class)]
final class PathScannerTest extends TestCase
{
    private PathScanner $scanner;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('path_scanner_test_');
        $this->scanner = new PathScanner($this->tempDir);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    public function testResolveSimpleDirectoryPath(): void
    {
        // Create a test directory
        $testDir = $this->tempDir . '/packages';
        mkdir($testDir, 0777, true);

        $result = $this->scanner->resolvePaths(['packages']);

        self::assertCount(1, $result);
        self::assertEquals(realpath($testDir), $result[0]);
    }

    public function testResolveNonExistentPath(): void
    {
        $result = $this->scanner->resolvePaths(['non-existent']);

        self::assertEmpty($result);
    }

    public function testResolveGlobPattern(): void
    {
        // Create test directories
        mkdir($this->tempDir . '/packages/ext1', 0777, true);
        mkdir($this->tempDir . '/packages/ext2', 0777, true);
        mkdir($this->tempDir . '/packages/ext3', 0777, true);

        $result = $this->scanner->resolvePaths(['packages/*']);

        self::assertCount(3, $result);
        self::assertContains(realpath($this->tempDir . '/packages/ext1'), $result);
        self::assertContains(realpath($this->tempDir . '/packages/ext2'), $result);
        self::assertContains(realpath($this->tempDir . '/packages/ext3'), $result);
    }

    public function testResolveVendorNamespacePattern(): void
    {
        // Create mock vendor structure
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir . '/cpsit/package1', 0777, true);
        mkdir($vendorDir . '/cpsit/package2', 0777, true);
        mkdir($vendorDir . '/fr/package3', 0777, true);

        $this->scanner->setVendorPath($vendorDir);

        $result = $this->scanner->resolvePaths(['cpsit/*']);

        self::assertCount(2, $result);
        self::assertContains(realpath($vendorDir . '/cpsit/package1'), $result);
        self::assertContains(realpath($vendorDir . '/cpsit/package2'), $result);
    }

    public function testResolveVendorNamespacePatternWithSubpath(): void
    {
        // Create mock vendor structure with Classes directories
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir . '/cpsit/package1/Classes', 0777, true);
        mkdir($vendorDir . '/cpsit/package2/Classes', 0777, true);
        // Package without Classes directory
        mkdir($vendorDir . '/cpsit/package3', 0777, true);

        $this->scanner->setVendorPath($vendorDir);

        $result = $this->scanner->resolvePaths(['cpsit/*/Classes']);

        self::assertCount(2, $result);
        self::assertContains(realpath($vendorDir . '/cpsit/package1/Classes'), $result);
        self::assertContains(realpath($vendorDir . '/cpsit/package2/Classes'), $result);
        // Should not include package3 since it doesn't have Classes directory
    }

    public function testResolveVendorPatternWithoutVendorPath(): void
    {
        // Don't set vendor path
        $result = $this->scanner->resolvePaths(['cpsit/*']);

        self::assertEmpty($result);
    }

    public function testResolveWithExclusionPatterns(): void
    {
        // Create test directories
        mkdir($this->tempDir . '/packages/good1', 0777, true);
        mkdir($this->tempDir . '/packages/good2', 0777, true);
        mkdir($this->tempDir . '/packages/legacy', 0777, true);

        $result = $this->scanner->resolvePaths(['packages/*', '!packages/legacy']);

        self::assertCount(2, $result);
        self::assertContains(realpath($this->tempDir . '/packages/good1'), $result);
        self::assertContains(realpath($this->tempDir . '/packages/good2'), $result);
        self::assertNotContains(realpath($this->tempDir . '/packages/legacy'), $result);
    }

    public function testResolveAbsolutePath(): void
    {
        // Create test directory
        $testDir = $this->tempDir . '/absolute-test';
        mkdir($testDir, 0777, true);

        $result = $this->scanner->resolvePaths([$testDir]);

        self::assertCount(1, $result);
        self::assertEquals(realpath($testDir), $result[0]);
    }

    public function testResolvePathsAreSorted(): void
    {
        // Create test directories in reverse order
        mkdir($this->tempDir . '/zzz', 0777, true);
        mkdir($this->tempDir . '/aaa', 0777, true);
        mkdir($this->tempDir . '/mmm', 0777, true);

        $result = $this->scanner->resolvePaths(['zzz', 'aaa', 'mmm']);

        // Should be sorted alphabetically
        self::assertCount(3, $result);
        self::assertTrue(strpos($result[0], 'aaa') !== false);
        self::assertTrue(strpos($result[1], 'mmm') !== false);
        self::assertTrue(strpos($result[2], 'zzz') !== false);
    }

    public function testResolvePathsRemovesDuplicates(): void
    {
        // Create test directory
        mkdir($this->tempDir . '/test', 0777, true);

        $result = $this->scanner->resolvePaths(['test', 'test', './test']);

        self::assertCount(1, $result);
    }

    public function testValidatePathsReturnsValidationInfo(): void
    {
        // Create valid directory
        $validDir = $this->tempDir . '/valid';
        mkdir($validDir, 0777, true);

        $validation = $this->scanner->validatePaths([
            $validDir,
            $this->tempDir . '/invalid',
        ]);

        self::assertArrayHasKey('valid', $validation);
        self::assertArrayHasKey('invalid', $validation);
        self::assertArrayHasKey('inaccessible', $validation);

        // The validation should contain the actual path passed, not necessarily the realpath
        self::assertContains(realpath($validDir), $validation['valid']);
        self::assertContains($this->tempDir . '/invalid', $validation['invalid']);
    }

    public function testGetPathResolutionDebugInfo(): void
    {
        $patterns = ['packages/*', 'src'];
        
        $debugInfo = $this->scanner->getPathResolutionDebugInfo($patterns);

        self::assertArrayHasKey('project_root', $debugInfo);
        self::assertArrayHasKey('vendor_path', $debugInfo);
        self::assertArrayHasKey('input_patterns', $debugInfo);
        self::assertArrayHasKey('resolved_paths', $debugInfo);
        self::assertArrayHasKey('pattern_analysis', $debugInfo);

        self::assertEquals($this->tempDir, $debugInfo['project_root']);
        self::assertEquals($patterns, $debugInfo['input_patterns']);
        self::assertIsArray($debugInfo['pattern_analysis']);
    }

    public function testPatternTypeDetection(): void
    {
        // Test via debug info which exposes pattern type detection
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir . '/cpsit', 0777, true); // Create cpsit namespace to make it vendor_namespace type
        $this->scanner->setVendorPath($vendorDir);

        $debugInfo = $this->scanner->getPathResolutionDebugInfo([
            'cpsit/*',      // vendor_namespace
            'packages/*',   // glob
            'src',          // direct_path
            '!legacy/*'     // glob (exclusion)
        ]);

        $analysis = $debugInfo['pattern_analysis'];
        
        self::assertEquals('vendor_namespace', $analysis[0]['type']);
        self::assertEquals('glob', $analysis[1]['type']); // packages/* is glob since packages dir doesn't exist in vendor
        self::assertEquals('direct_path', $analysis[2]['type']);
        self::assertEquals('glob', $analysis[3]['type']); // legacy/* is glob
        
        self::assertFalse($analysis[0]['is_exclusion']);
        self::assertFalse($analysis[1]['is_exclusion']);
        self::assertFalse($analysis[2]['is_exclusion']);
        self::assertTrue($analysis[3]['is_exclusion']);
    }

    public function testClearCache(): void
    {
        // Create directory and resolve paths to populate cache
        mkdir($this->tempDir . '/test', 0777, true);
        
        $result1 = $this->scanner->resolvePaths(['test']);
        self::assertNotEmpty($result1);

        // Clear cache
        $this->scanner->clearCache();
        
        // Remove directory and try again - should re-scan
        TestHelper::removeDirectory($this->tempDir . '/test');
        
        $result2 = $this->scanner->resolvePaths(['test']);
        self::assertEmpty($result2);
    }

    public function testExclusionWithGlobPattern(): void
    {
        // Create test structure
        mkdir($this->tempDir . '/packages/good', 0777, true);
        mkdir($this->tempDir . '/packages/legacy1', 0777, true);
        mkdir($this->tempDir . '/packages/legacy2', 0777, true);

        $result = $this->scanner->resolvePaths([
            'packages/*',
            '!packages/legacy*'
        ]);

        self::assertCount(1, $result);
        self::assertContains(realpath($this->tempDir . '/packages/good'), $result);
    }

    public function testVendorPathUpdate(): void
    {
        // Create vendor structure
        $vendorDir1 = $this->tempDir . '/vendor1';
        $vendorDir2 = $this->tempDir . '/vendor2';
        mkdir($vendorDir1 . '/cpsit/package1', 0777, true);
        mkdir($vendorDir2 . '/cpsit/package2', 0777, true);

        // Set initial vendor path
        $this->scanner->setVendorPath($vendorDir1);
        $result1 = $this->scanner->resolvePaths(['cpsit/*']);
        
        // Update vendor path
        $this->scanner->setVendorPath($vendorDir2);
        $result2 = $this->scanner->resolvePaths(['cpsit/*']);

        self::assertNotEquals($result1, $result2);
        self::assertContains(realpath($vendorDir1 . '/cpsit/package1'), $result1);
        self::assertContains(realpath($vendorDir2 . '/cpsit/package2'), $result2);
    }

    public function testCacheKeyUniqueness(): void
    {
        // Create test directories
        mkdir($this->tempDir . '/test1', 0777, true);
        mkdir($this->tempDir . '/test2', 0777, true);

        // Test that different patterns generate different cache entries
        $result1 = $this->scanner->resolvePaths(['test1']);
        $result2 = $this->scanner->resolvePaths(['test2']);
        $result3 = $this->scanner->resolvePaths(['test1', 'test2']);

        // Results should be different
        self::assertNotEquals($result1, $result2);
        self::assertNotEquals($result1, $result3);
        self::assertNotEquals($result2, $result3);

        // Test caching works - same patterns should return same results without re-scanning
        $cachedResult1 = $this->scanner->resolvePaths(['test1']);
        self::assertEquals($result1, $cachedResult1);

        // Test vendor path affects cache key
        $vendorDir = $this->tempDir . '/vendor';
        mkdir($vendorDir, 0777, true);
        
        $this->scanner->setVendorPath($vendorDir);
        $resultWithVendor = $this->scanner->resolvePaths(['test1']);
        
        // Should be same paths but cache should be cleared due to vendor path change
        self::assertEquals($result1, $resultWithVendor);
    }
}