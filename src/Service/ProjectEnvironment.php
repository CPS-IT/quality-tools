<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

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

    private ?string $projectRoot = null;

    public function __construct(
        private readonly VendorDirectoryDetector $vendorDetector,
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
