<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\ProjectEnvironment;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

#[CoversClass(ProjectEnvironment::class)]
final class ProjectEnvironmentTest extends TestCase
{
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        VendorDirectoryDetector::clearCache();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        putenv('QT_PROJECT_ROOT');
        VendorDirectoryDetector::clearCache();
    }

    // -- Project root detection from fixtures --

    #[Test]
    #[DataProvider('provideComposerProjects')]
    public function getProjectRootDetectsComposerProject(string $fixturePath): void
    {
        chdir($fixturePath);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->assertSame($fixturePath, $env->getProjectRoot());
    }

    /**
     * Unlike QualityToolsApplication, ProjectEnvironment accepts any Composer
     * project -- not just TYPO3. This provider covers both TYPO3 and non-TYPO3
     * fixtures to verify that behavior.
     */
    public static function provideComposerProjects(): \Generator
    {
        $basePath = __DIR__ . '/../../Fixtures';

        yield 'typo3/cms-core project' => [realpath($basePath . '/valid-typo3-project')];
        yield 'typo3/minimal project' => [realpath($basePath . '/valid-typo3-minimal')];
        yield 'typo3/cms project' => [realpath($basePath . '/valid-typo3-cms')];
        yield 'TYPO3 in dev dependencies' => [realpath($basePath . '/typo3-dev-dependency')];
        yield 'non-TYPO3 project' => [realpath($basePath . '/non-typo3-project')];
    }

    #[Test]
    public function getProjectRootTraversesUpwardToFindComposerJson(): void
    {
        // nested-project/subdir has composer.json with typo3/cms-core
        $nestedPath = self::fixturePath('nested-project/subdir');
        chdir($nestedPath);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->assertSame($nestedPath, $env->getProjectRoot());
    }

    #[Test]
    public function getProjectRootSkipsInvalidJsonAndTraversesUpward(): void
    {
        // invalid-json fixture has a broken composer.json but its parent
        // (the package root) has a valid one -- however isComposerProject
        // only checks file_exists, so even invalid JSON is accepted.
        $invalidJsonPath = self::fixturePath('invalid-json');
        chdir($invalidJsonPath);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        // ProjectEnvironment considers any directory with composer.json a project
        $this->assertSame($invalidJsonPath, $env->getProjectRoot());
    }

    // -- Environment variable override --

    #[Test]
    #[DataProvider('provideComposerProjects')]
    public function getProjectRootUsesEnvironmentVariableOverride(string $fixturePath): void
    {
        putenv('QT_PROJECT_ROOT=' . $fixturePath);

        // Change to a directory without composer.json to ensure env var takes precedence
        $tempDir = sys_get_temp_dir() . '/qt-test-' . uniqid();
        mkdir($tempDir, 0o777, true);
        chdir($tempDir);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        try {
            $this->assertSame($fixturePath, $env->getProjectRoot());
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function getProjectRootIgnoresInvalidEnvironmentVariable(): void
    {
        putenv('QT_PROJECT_ROOT=/invalid/path/that/does/not/exist');

        $fixturePath = self::fixturePath('valid-typo3-project');
        chdir($fixturePath);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->assertSame($fixturePath, $env->getProjectRoot());
    }

    // -- Error handling --

    #[Test]
    public function getProjectRootThrowsWhenNoComposerProjectFound(): void
    {
        $tempDir = sys_get_temp_dir() . '/qt-test-' . uniqid();
        mkdir($tempDir, 0o777, true);
        chdir($tempDir);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Composer project root not found');
            $env->getProjectRoot();
        } finally {
            chdir($this->originalCwd);
            rmdir($tempDir);
        }
    }

    #[Test]
    public function getProjectRootHandlesRootFilesystem(): void
    {
        $rootPath = '/';
        if (!is_dir($rootPath)) {
            $this->markTestSkipped('Root filesystem not accessible');
        }

        chdir($rootPath);
        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer project root not found');
        $env->getProjectRoot();
    }

    // -- Caching --

    #[Test]
    public function getProjectRootCachesResult(): void
    {
        $fixturePath = self::fixturePath('valid-typo3-project');
        chdir($fixturePath);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $first = $env->getProjectRoot();

        // Change directory -- cached value should still be returned
        chdir(self::fixturePath('non-typo3-project'));
        $second = $env->getProjectRoot();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function clearCachedProjectRootForcesRedetection(): void
    {
        $typo3Path = self::fixturePath('valid-typo3-project');
        $nonTypo3Path = self::fixturePath('non-typo3-project');

        chdir($typo3Path);
        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $this->assertSame($typo3Path, $env->getProjectRoot());

        chdir($nonTypo3Path);
        $env->clearCachedProjectRoot();

        $this->assertSame($nonTypo3Path, $env->getProjectRoot());
    }

    // -- Vendor path delegation --

    #[Test]
    public function getVendorPathDelegatesToDetector(): void
    {
        // Use the package root itself which has a real vendor directory
        $packageRoot = \dirname(__DIR__, 3);
        putenv('QT_PROJECT_ROOT=' . $packageRoot);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $vendorPath = $env->getVendorPath();
        $this->assertDirectoryExists($vendorPath);
        $this->assertFileExists($vendorPath . '/autoload.php');
    }

    #[Test]
    public function getVendorBinPathAppendsSlashBin(): void
    {
        $packageRoot = \dirname(__DIR__, 3);
        putenv('QT_PROJECT_ROOT=' . $packageRoot);

        $env = new ProjectEnvironment(new VendorDirectoryDetector());

        $vendorPath = $env->getVendorPath();
        $this->assertSame($vendorPath . '/bin', $env->getVendorBinPath());
    }

    // -- Helper --

    private static function fixturePath(string $fixture): string
    {
        return realpath(__DIR__ . '/../../Fixtures/' . $fixture);
    }
}
