<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Composer\Autoload\ClassLoader;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Provides project environment information (paths) to tool runners.
 *
 * Replaces scattered path detection in QualityToolsApplication and BaseCommand
 * with a single composable service. Supports any Composer project, not just TYPO3.
 */
final class ProjectEnvironment
{
    private const int MAX_TRAVERSAL_LEVELS = 10;
    private const string ENV_PROJECT_ROOT = 'QT_PROJECT_ROOT';
    private const string ENV_TOOL_BIN_PATH = 'QT_TOOL_BIN_PATH';

    private ?string $projectRoot = null;

    public function __construct(
        private readonly VendorDirectoryDetector $vendorDetector,
        private readonly ?string $toolBinPath = null,
    ) {
    }

    public function getProjectRoot(): string
    {
        $this->projectRoot ??= $this->detectProjectRoot();

        return $this->projectRoot;
    }

    public function getVendorPath(): string
    {
        return $this->vendorDetector->detectVendorPath($this->getProjectRoot());
    }

    public function getVendorBinPath(): string
    {
        return $this->getVendorPath() . '/bin';
    }

    /**
     * Returns the bin directory of the installation qt itself runs from.
     *
     * The analysis tools are dependencies of qt, so they are always taken from
     * here and never from the project. This keeps them out of the project's
     * dependency tree, where they would compete with production packages for
     * versions.
     */
    public function getToolBinPath(): string
    {
        if ($this->toolBinPath !== null) {
            return $this->toolBinPath;
        }

        $envToolBinPath = getenv(self::ENV_TOOL_BIN_PATH);
        if ($envToolBinPath !== false && is_dir($envToolBinPath)) {
            return realpath($envToolBinPath) ?: $envToolBinPath;
        }

        // Set by Composer's bin proxy (vendor/bin/qt) since Composer 2.2
        $composerBinDir = $GLOBALS['_composer_bin_dir'] ?? null;
        if (\is_string($composerBinDir) && is_dir($composerBinDir)) {
            return realpath($composerBinDir) ?: $composerBinDir;
        }

        $classLoaderFile = (new \ReflectionClass(ClassLoader::class))->getFileName();
        if ($classLoaderFile === false) {
            throw new RuntimeException('Unable to locate the quality-tools installation.');
        }

        return \dirname($classLoaderFile, 2) . '/bin';
    }

    /**
     * Returns the project's Composer autoloader if the tools run from a
     * separate installation, so that tools which need the project's classes
     * (Rector) can load them. Returns null if qt is installed in the project
     * itself, where the tools already use that autoloader.
     */
    public function getProjectAutoloadFile(): ?string
    {
        try {
            $vendorPath = $this->getVendorPath();
        } catch (\Throwable) {
            return null;
        }

        $autoloadFile = $vendorPath . '/autoload.php';
        if (!is_file($autoloadFile)) {
            return null;
        }

        $toolVendorPath = realpath(\dirname($this->getToolBinPath()));
        if ($toolVendorPath !== false && $toolVendorPath === realpath($vendorPath)) {
            return null;
        }

        return $autoloadFile;
    }

    /**
     * Returns the absolute path to this package's bundled config directory.
     *
     * Uses __DIR__-relative resolution so the path is correct regardless of
     * whether the package is installed as a vendor dependency or used as the
     * Composer project root (e.g. during the package's own test runs).
     */
    public function getPackageConfigDir(): string
    {
        return \dirname(__DIR__, 2) . '/config';
    }

    /**
     * Clear cached project root to force re-detection.
     */
    public function clearCachedProjectRoot(): void
    {
        $this->projectRoot = null;
    }

    private function detectProjectRoot(): string
    {
        $envRoot = $this->detectFromEnvironment();
        if ($envRoot !== null) {
            return $envRoot;
        }

        return $this->detectFromFilesystem();
    }

    private function detectFromEnvironment(): ?string
    {
        $envProjectRoot = getenv(self::ENV_PROJECT_ROOT);
        if ($envProjectRoot !== false && is_dir($envProjectRoot)) {
            $resolved = realpath($envProjectRoot);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        return null;
    }

    private function detectFromFilesystem(): string
    {
        $currentDir = getcwd();
        if ($currentDir === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        $searchDir = $currentDir;

        for ($i = 0; $i < self::MAX_TRAVERSAL_LEVELS; ++$i) {
            if ($this->isComposerProject($searchDir)) {
                return $searchDir;
            }

            $parentDir = \dirname($searchDir);
            if ($parentDir === $searchDir) {
                break;
            }

            $searchDir = $parentDir;
        }

        throw new RuntimeException('Composer project root not found. Please run this command from within a Composer project directory, or set the ' . self::ENV_PROJECT_ROOT . ' environment variable.');
    }

    private function isComposerProject(string $directory): bool
    {
        return file_exists($directory . '/composer.json');
    }
}
