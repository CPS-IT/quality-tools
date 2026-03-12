<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

/**
 * Service for extracting project-level configuration data.
 *
 * Avoids duplication between SimpleConfiguration and EnhancedConfiguration
 * by centralizing project configuration logic (Step 4.1 of Issue 019).
 */
final readonly class ProjectConfigService
{
    public function __construct()
    {
    }

    /**
     * Get PHP version from configuration data.
     */
    public function getPhpVersion(array $data): string
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['php_version'] ?? '8.3';
    }

    /**
     * Get TYPO3 version from configuration data.
     */
    public function getTypo3Version(array $data): string
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['typo3_version'] ?? '13.4';
    }

    /**
     * Get the project name from configuration data.
     */
    public function getProjectName(array $data): ?string
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['name'] ?? null;
    }

    /**
     * Get verbosity level from configuration data.
     */
    public function getVerbosity(array $data): string
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['verbosity'] ?? 'normal';
    }

    /**
     * Check if colors are enabled from configuration data.
     */
    public function isColorsEnabled(array $data): bool
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['colors'] ?? true;
    }

    /**
     * Check if progress is enabled from configuration data.
     */
    public function isProgressEnabled(array $data): bool
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['progress'] ?? true;
    }

    /**
     * Check if parallel processing is enabled from configuration data.
     */
    public function isParallelEnabled(array $data): bool
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['parallel'] ?? true;
    }

    /**
     * Get the maximum number of parallel processes from configuration data.
     */
    public function getMaxProcesses(array $data): int
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['max_processes'] ?? 4;
    }

    /**
     * Check if cache is enabled from configuration data.
     */
    public function isCacheEnabled(array $data): bool
    {
        $qualityTools = $data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['cache_enabled'] ?? true;
    }

    /**
     * Extract all project configuration as a structured array.
     */
    public function getProjectConfig(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];

        return $qualityTools['project'] ?? [];
    }

    /**
     * Extract all output configuration as a structured array.
     */
    public function getOutputConfig(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];

        return $qualityTools['output'] ?? [];
    }

    /**
     * Extract all performance configuration as a structured array.
     */
    public function getPerformanceConfig(array $data): array
    {
        $qualityTools = $data['quality-tools'] ?? [];

        return $qualityTools['performance'] ?? [];
    }
}
