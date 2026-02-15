<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Utility\PathScanner;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;

/**
 * Service for extracting path scanning and resolution logic.
 *
 * Eliminates duplication between SimpleConfiguration and EnhancedConfiguration
 * by centralizing path resolution logic (Step 4.1 of Issue 019).
 */
final class PathResolutionService
{
    private ?string $vendorPath = null;
    private ?PathScanner $pathScanner = null;

    public function __construct(
        private readonly ?VendorDirectoryDetector $vendorDetector = null,
    ) {
    }

    /**
     * Get scan paths from configuration data.
     */
    public function getScanPaths(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $pathsConfig = $qualityTools['paths'] ?? [];
        $scanPaths = $pathsConfig['scan'] ?? ['packages/', 'config/system/'];

        return array_map([$this, 'normalizePath'], $scanPaths);
    }

    /**
     * Get exclude paths from configuration data.
     */
    public function getExcludePaths(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $pathsConfig = $qualityTools['paths'] ?? [];
        $excludePaths = $pathsConfig['exclude'] ?? ['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'];

        return array_map([$this, 'normalizePath'], $excludePaths);
    }

    /**
     * Get resolved paths for a specific tool.
     */
    public function getResolvedPathsForTool(array $data, string $tool, string $projectRoot): array
    {
        $toolPaths = $this->getToolPaths($data, $tool);

        if (!empty($toolPaths)) {
            return $toolPaths;
        }

        $pathScanner = $this->getPathScanner($projectRoot);
        $scanPaths = $this->getScanPaths($data);

        // Use the PathScanner to resolve the scan paths
        return $pathScanner->resolvePaths($scanPaths);
    }

    /**
     * Get tool-specific paths from configuration data.
     */
    public function getToolPaths(array $data, string $tool): array
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        return $toolsConfig[$tool]['paths'] ?? [];
    }

    /**
     * Get vendor directory path.
     */
    public function getVendorPath(string $projectRoot): ?string
    {
        if ($this->vendorPath === null) {
            try {
                $detector = $this->getVendorDetector();
                $this->vendorPath = $detector->detectVendorPath($projectRoot);
            } catch (VendorDirectoryNotFoundException) {
                // Return null if detection fails - calling code can handle this
                return null;
            }
        }

        return $this->vendorPath;
    }

    /**
     * Get vendor bin directory path.
     */
    public function getVendorBinPath(string $projectRoot): ?string
    {
        $vendorPath = $this->getVendorPath($projectRoot);

        return $vendorPath !== null ? $vendorPath . '/bin' : null;
    }

    /**
     * Get all paths configuration as a structured array.
     */
    public function getPathsConfig(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];

        return $qualityTools['paths'] ?? [];
    }

    /**
     * Clear cached vendor path (used when project root changes).
     */
    public function clearVendorPathCache(): void
    {
        $this->vendorPath = null;
    }

    /**
     * Clear cached path scanner (used when project root changes).
     */
    public function clearPathScannerCache(): void
    {
        $this->pathScanner = null;
    }

    /**
     * Clear all cached data.
     */
    public function clearAllCaches(): void
    {
        $this->clearVendorPathCache();
        $this->clearPathScannerCache();
    }

    /**
     * Get or create vendor directory detector instance.
     */
    private function getVendorDetector(): VendorDirectoryDetector
    {
        return $this->vendorDetector ?? new VendorDirectoryDetector();
    }

    /**
     * Get or create path scanner instance.
     */
    private function getPathScanner(string $projectRoot): PathScanner
    {
        if ($this->pathScanner === null) {
            $this->pathScanner = new PathScanner($projectRoot);
        }

        return $this->pathScanner;
    }

    /**
     * Get additional paths from configuration (Feature 013).
     */
    public function getAdditionalPaths(array $data): array
    {
        $pathsConfig = $this->getPathsConfig($data);

        return $pathsConfig['additional'] ?? [];
    }

    /**
     * Get exclude patterns from configuration (Feature 013).
     */
    public function getExcludePatterns(array $data): array
    {
        $pathsConfig = $this->getPathsConfig($data);

        return $pathsConfig['exclude_patterns'] ?? [];
    }

    /**
     * Get tool-specific path overrides from configuration (Feature 013).
     */
    public function getToolPathOverrides(array $data, string $tool): array
    {
        $pathsConfig = $this->getPathsConfig($data);
        $toolOverrides = $pathsConfig['tool_overrides'] ?? [];

        return $toolOverrides[$tool] ?? [];
    }

    /**
     * Normalize path by removing ./ prefix and ensuring consistent format.
     */
    private function normalizePath(string $path): string
    {
        // Remove leading "./"
        $path = preg_replace('#^\./+#', '', $path);
        
        return $path;
    }
}
