<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;

/**
 * Enhanced configuration with source tracking and hierarchical support.
 *
 * Extends the base Configuration class with Feature 015 capabilities.
 * @deprecated Use Configuration instead.
 */
final class EnhancedConfiguration implements ConfigurationInterface
{
    private string $actualProjectRoot;
    private ?string $vendorPath = null;
    private ?VendorDirectoryDetector $vendorDetector = null;

    public function __construct(
        private readonly array $data = [],
        private readonly array $sourceMap = [],
        private readonly array $conflicts = [],
        private readonly array $mergeSummary = [],
        private readonly ?ConfigurationHierarchy $hierarchy = null,
        private readonly ?ConfigurationDiscovery $discovery = null,
        private readonly ?string $projectRoot = null,
        private readonly ?ConfigurationValidator $validator = null,
        private readonly ?PathResolutionService $pathResolutionService = null,
    ) {
        // Validate configuration if validator is provided and data is not empty
        if ($this->validator !== null && !empty($this->data)) {
            $this->validator->validate($this->data);
        }

        if ($this->projectRoot !== null) {
            $this->setProjectRoot($this->projectRoot);
        }
    }

    public function setProjectRoot(string $projectRoot): void
    {
        $this->actualProjectRoot = $projectRoot;
        $this->vendorPath = null; // Reset vendor path cache
    }

    public function getProjectRoot(): ?string
    {
        return $this->actualProjectRoot ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getProjectPhpVersion(): string
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['php_version'] ?? '8.3';
    }

    public function getProjectTypo3Version(): string
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['typo3_version'] ?? '13.4';
    }

    public function getProjectName(): ?string
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $projectConfig = $qualityTools['project'] ?? [];

        return $projectConfig['name'] ?? null;
    }

    public function getScanPaths(): array
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $pathsConfig = $qualityTools['paths'] ?? [];

        return $pathsConfig['scan'] ?? ['packages/', 'config/system/'];
    }

    public function getExcludePaths(): array
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $pathsConfig = $qualityTools['paths'] ?? [];

        return $pathsConfig['exclude'] ?? ['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'];
    }

    public function getToolPaths(string $tool): array
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        return $toolsConfig[$tool]['paths'] ?? [];
    }

    public function isToolEnabled(string $tool): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        return $toolsConfig[$tool]['enabled'] ?? true;
    }

    public function getToolConfig(string $tool): array
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];
        $config = $toolsConfig[$tool] ?? [];

        // Apply tool-specific defaults for backward compatibility
        return match ($tool) {
            'phpstan' => $this->getPhpStanConfig($config),
            'rector' => $this->getRectorConfig($config),
            'fractor' => $this->getFractorConfig($config),
            'php-cs-fixer' => $this->getPhpCsFixerConfig($config),
            default => $config,
        };
    }

    private function getPhpStanConfig(array $config = []): array
    {
        return array_merge([
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ], $config);
    }

    private function getRectorConfig(array $config = []): array
    {
        return array_merge([
            'enabled' => true,
            'level' => 'typo3-13',
            'php_version' => $this->getProjectPhpVersion(),
        ], $config);
    }

    private function getFractorConfig(array $config = []): array
    {
        return array_merge([
            'enabled' => true,
            'php_version' => $this->getProjectPhpVersion(),
        ], $config);
    }

    private function getPhpCsFixerConfig(array $config = []): array
    {
        return array_merge([
            'enabled' => true,
            'preset' => 'typo3',
        ], $config);
    }

    /**
     * Get the source that provided a specific configuration value.
     */
    public function getConfigurationSource(string $keyPath): ?string
    {
        return $this->sourceMap[$keyPath] ?? null;
    }

    /**
     * Get all configuration sources with their metadata.
     */
    public function getConfigurationSources(): array
    {
        $sources = [];

        foreach ($this->sourceMap as $keyPath => $source) {
            if (!isset($sources[$source])) {
                $sources[$source] = [
                    'source' => $source,
                    'keys' => [],
                ];
            }
            $sources[$source]['keys'][] = $keyPath;
        }

        return $sources;
    }

    /**
     * Get configuration conflicts that occurred during merging.
     */
    public function getConfigurationConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * Check if there were any configuration conflicts.
     */
    public function hasConfigurationConflicts(): bool
    {
        return !empty($this->conflicts);
    }

    /**
     * Get conflicts for a specific configuration key.
     */
    public function getConflictsForKey(string $keyPath): array
    {
        return array_filter(
            $this->conflicts,
            fn (array $conflict): bool => $conflict['key_path'] === $keyPath,
        );
    }

    /**
     * Get merge summary with statistics.
     */
    public function getMergeSummary(): array
    {
        return $this->mergeSummary;
    }

    /**
     * Check if a tool uses a custom configuration file.
     */
    public function usesCustomConfigFile(string $tool): bool
    {
        $toolConfig = $this->getToolConfig($tool);

        return isset($toolConfig['use_custom_config']) && $toolConfig['use_custom_config'] === true;
    }

    /**
     * Get the path to a tool's custom configuration file.
     */
    public function getCustomConfigFilePath(string $tool): ?string
    {
        $toolConfig = $this->getToolConfig($tool);

        return $toolConfig['config_file'] ?? null;
    }

    /**
     * Get configuration with source attribution for debugging.
     */
    public function getConfigurationWithSources(): array
    {
        $config = $this->toArray();
        $withSources = [];

        $this->addSourceAttributionRecursive($config, $withSources, []);

        return $withSources;
    }

    /**
     * Add source attribution recursively to configuration data.
     */
    private function addSourceAttributionRecursive(
        array $data,
        array &$target,
        array $keyPath,
    ): void {
        foreach ($data as $key => $value) {
            $currentKeyPath = array_merge($keyPath, [$key]);
            $keyPathStr = implode('.', $currentKeyPath);

            if (\is_array($value)) {
                $target[$key] = [];
                $this->addSourceAttributionRecursive($value, $target[$key], $currentKeyPath);
            } else {
                $target[$key] = [
                    'value' => $value,
                    'source' => $this->getConfigurationSource($keyPathStr),
                ];
            }
        }
    }

    /**
     * Get tool-specific configuration with override handling.
     */
    public function getToolConfigurationResolved(string $tool): array
    {
        // If tool uses custom config file, don't merge with unified config
        if ($this->usesCustomConfigFile($tool)) {
            $customConfigPath = $this->getCustomConfigFilePath($tool);

            return [
                'use_custom_config' => true,
                'config_file' => $customConfigPath,
                'unified_config_ignored' => true,
            ];
        }

        // Return normal tool configuration
        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        return $toolsConfig[$tool] ?? [];
    }

    /**
     * Get hierarchy information if available.
     */
    public function getHierarchyInfo(): ?array
    {
        return $this->hierarchy?->getDebugInfo();
    }

    /**
     * Get discovery information if available.
     */
    public function getDiscoveryInfo(): ?array
    {
        return $this->discovery?->getDiscoveryDebugInfo();
    }

    /**
     * Check if hierarchical configuration is active.
     */
    public function isHierarchicalConfiguration(): bool
    {
        return $this->hierarchy !== null && $this->discovery !== null;
    }

    /**
     * Get all tools that have custom configuration files.
     */
    public function getToolsWithCustomConfigs(): array
    {
        $tools = [];

        foreach (['rector', 'phpstan', 'php-cs-fixer', 'fractor', 'typoscript-lint'] as $tool) {
            if ($this->usesCustomConfigFile($tool)) {
                $tools[$tool] = $this->getCustomConfigFilePath($tool);
            }
        }

        return $tools;
    }

    /**
     * Get comprehensive debug information.
     */
    public function getComprehensiveDebugInfo(): array
    {
        $debugInfo = [
            'is_hierarchical' => $this->isHierarchicalConfiguration(),
            'project_root' => $this->getProjectRoot(),
            'configuration_sources' => $this->getConfigurationSources(),
            'has_conflicts' => $this->hasConfigurationConflicts(),
            'conflicts_count' => \count($this->conflicts),
            'merge_summary' => $this->getMergeSummary(),
            'tools_with_custom_configs' => $this->getToolsWithCustomConfigs(),
        ];

        if ($this->isHierarchicalConfiguration()) {
            $debugInfo['hierarchy_info'] = $this->getHierarchyInfo();
            $debugInfo['discovery_info'] = $this->getDiscoveryInfo();
        }

        if ($this->hasConfigurationConflicts()) {
            $debugInfo['conflicts'] = $this->getConfigurationConflicts();
        }

        return $debugInfo;
    }

    /**
     * Export configuration with full metadata for debugging.
     */
    public function exportWithMetadata(): array
    {
        return [
            'configuration' => $this->toArray(),
            'source_map' => $this->sourceMap,
            'conflicts' => $this->conflicts,
            'merge_summary' => $this->mergeSummary,
            'debug_info' => $this->getComprehensiveDebugInfo(),
        ];
    }

    /**
     * Create an enhanced configuration from any configuration interface.
     */
    public static function fromConfiguration(ConfigurationInterface $config): self
    {
        return new self(
            data: $config->toArray(),
            sourceMap: [],
            conflicts: [],
            mergeSummary: [],
            hierarchy: null,
            discovery: null,
            projectRoot: $config->getProjectRoot(),
            validator: null,
        );
    }

    /**
     * Check if a configuration value was overridden by a higher priority source.
     */
    public function wasValueOverridden(string $keyPath): bool
    {
        return !empty($this->getConflictsForKey($keyPath));
    }

    /**
     * Get the configuration chain for a specific key (all sources that provided values).
     */
    public function getConfigurationChain(string $keyPath): array
    {
        $chain = [];
        $conflicts = $this->getConflictsForKey($keyPath);

        foreach ($conflicts as $conflict) {
            $chain[] = [
                'source' => $conflict['existing_source'],
                'value' => $conflict['existing_value'],
                'overridden' => true,
            ];
        }

        // Add final value
        $finalSource = $this->getConfigurationSource($keyPath);
        if ($finalSource !== null) {
            $chain[] = [
                'source' => $finalSource,
                'value' => $this->getValueByKeyPath($keyPath),
                'overridden' => false,
            ];
        }

        return $chain;
    }

    /**
     * Get a configuration value by dot-notation key path.
     */
    private function getValueByKeyPath(string $keyPath): mixed
    {
        $keys = explode('.', $keyPath);
        $value = $this->toArray();

        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    // Methods from Configuration interface that EnhancedConfiguration needs to implement

    public function getVerbosity(): string
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['verbosity'] ?? 'normal';
    }

    public function isColorsEnabled(): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['colors'] ?? true;
    }

    public function isProgressEnabled(): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $outputConfig = $qualityTools['output'] ?? [];

        return $outputConfig['progress'] ?? true;
    }

    public function isParallelEnabled(): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['parallel'] ?? true;
    }

    public function getMaxProcesses(): int
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['max_processes'] ?? 4;
    }

    public function isCacheEnabled(): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['cache_enabled'] ?? true;
    }

    public function getVendorPath(): ?string
    {
        if ($this->vendorPath === null && isset($this->actualProjectRoot)) {
            try {
                $detector = $this->getVendorDetector();
                $this->vendorPath = $detector->detectVendorPath($this->actualProjectRoot);
            } catch (VendorDirectoryNotFoundException) {
                // Return null if detection fails - calling code can handle this
                return null;
            }
        }

        return $this->vendorPath;
    }

    public function getVendorBinPath(): ?string
    {
        $vendorPath = $this->getVendorPath();

        return $vendorPath !== null ? $vendorPath . '/bin' : null;
    }

    public function hasVendorDirectory(): bool
    {
        return $this->getVendorPath() !== null;
    }

    public function getVendorDetectionDebugInfo(): array
    {
        if (!isset($this->actualProjectRoot)) {
            return ['error' => 'Project root not set'];
        }

        return $this->getVendorDetector()->getDetectionDebugInfo($this->actualProjectRoot);
    }

    public function getResolvedPathsForTool(string $tool): array
    {
        if (!isset($this->actualProjectRoot)) {
            return $this->getScanPaths(); // Fallback to standard paths
        }

        if ($this->pathResolutionService !== null) {
            // Use injected service if available
            return $this->pathResolutionService->getResolvedPathsForTool(
                $this->data,
                $tool,
                $this->actualProjectRoot,
            );
        }

        // Fallback: return basic scan paths if service not available
        return $this->getScanPaths();
    }

    private function getVendorDetector(): VendorDirectoryDetector
    {
        if ($this->vendorDetector === null) {
            $this->vendorDetector = new VendorDirectoryDetector();
        }

        return $this->vendorDetector;
    }

    public function getPathScanningDebugInfo(string $tool): array
    {
        if (!isset($this->actualProjectRoot)) {
            return ['error' => 'Project root not set'];
        }

        if ($this->pathResolutionService !== null) {
            // Use injected service for path resolution
            $resolvedPaths = $this->pathResolutionService->getResolvedPathsForTool(
                $this->data,
                $tool,
                $this->actualProjectRoot,
            );

            return [
                'tool' => $tool,
                'project_root' => $this->actualProjectRoot,
                'vendor_path' => $this->getVendorPath(),
                'global_scan_paths' => $this->pathResolutionService->getScanPaths($this->data),
                'global_exclude_paths' => $this->pathResolutionService->getExcludePaths($this->data),
                'tool_paths' => $this->pathResolutionService->getToolPaths($this->data, $tool),
                'resolved_paths' => $resolvedPaths,
                'service_status' => 'path_resolution_service_used',
            ];
        }

        // Fallback debug info if service not available
        return [
            'tool' => $tool,
            'project_root' => $this->actualProjectRoot,
            'vendor_path' => $this->getVendorPath(),
            'global_scan_paths' => $this->getScanPaths(),
            'global_exclude_paths' => $this->getExcludePaths(),
            'tool_paths' => $this->getToolPaths($tool),
            'resolved_paths' => $this->getResolvedPathsForTool($tool),
            'service_status' => 'path_resolution_service_not_available',
        ];
    }

    public function merge(ConfigurationInterface $other): ConfigurationInterface
    {
        $mergedData = array_merge_recursive($this->data, $other->toArray());

        return new self(
            data: $mergedData,
            sourceMap: $this->sourceMap,
            conflicts: $this->conflicts,
            mergeSummary: $this->mergeSummary,
            hierarchy: $this->hierarchy,
            discovery: $this->discovery,
            projectRoot: $this->actualProjectRoot ?? null,
            validator: $this->validator,
        );
    }
}
