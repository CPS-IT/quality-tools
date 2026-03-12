<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unified configuration class supporting both simple and hierarchical modes.
 *
 * This class replaces both SimpleConfiguration and EnhancedConfiguration,
 * providing all capabilities through a single, unified interface.
 * Business logic is delegated to specialized services for maintainability.
 */
final readonly class Configuration implements ConfigurationInterface
{
    public function __construct(
        private string $projectRoot,
        private array $data = [],
        private array $sourceMap = [],
        private array $conflicts = [],
        private array $mergeSummary = [],
        private bool $hierarchicalMode = false,
        private ?ConfigurationValidator $validator = null,
        private ?ProjectConfigService $projectConfigService = null,
        private ?ToolConfigService $toolConfigService = null,
        private ?PathResolutionService $pathResolutionService = null,
        private ?ConfigurationHierarchy $hierarchy = null,
        private ?ConfigurationDiscovery $discovery = null,
    ) {
        // Store validator but don't validate immediately to match wrapper behavior
        // Validation will happen through wrapper or explicit calls

        // Clear path resolution cache
        $this->pathResolutionService?->clearAllCaches();
    }

    public function validateConfiguration(): void
    {
        if ($this->validator !== null && !empty($this->data)) {
            try {
                $this->validator->validate($this->data);
            } catch (\Exception $e) {
                // Log validation errors but don't throw to match wrapper permissiveness
                error_log('Configuration validation warning: ' . $e->getMessage());
            }
        }
    }

    // Core data access methods

    public function toArray(): array
    {
        return $this->data;
    }

    public function setProjectRoot(string $projectRoot): void
    {
        // Since projectRoot is readonly, we cannot modify it after construction
        // This method exists to satisfy the interface but throws an exception
        if ($this->projectRoot !== $projectRoot) {
            throw new \InvalidArgumentException('Cannot change project root after construction. Project root is immutable in this implementation.');
        }
        // If the same project root is set, do nothing (idempotent)
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    // Project configuration methods (delegated to ProjectConfigService)

    public function getProjectPhpVersion(): string
    {
        return $this->projectConfigService?->getPhpVersion($this->data)
            ?? self::DEFAULT_PHP_VERSION;
    }

    public function getProjectTypo3Version(): string
    {
        return $this->projectConfigService?->getTypo3Version($this->data)
            ?? self::DEFAULT_TYPO3_VERSION;
    }

    public function getProjectName(): ?string
    {
        return $this->projectConfigService?->getProjectName($this->data);
    }

    // Path configuration methods (delegated to PathResolutionService)

    public function getScanPaths(): array
    {
        return $this->pathResolutionService?->getScanPaths($this->data)
            ?? self::DEFAULT_SCAN_PATHS;
    }

    public function getExcludePaths(): array
    {
        return $this->pathResolutionService?->getExcludePaths($this->data)
            ?? self::DEFAULT_EXCLUDE_PATHS;
    }

    public function getToolPaths(string $tool): array
    {
        return $this->pathResolutionService?->getToolPaths($this->data, $tool)
            ?? [];
    }

    // Tool configuration methods (delegated to ToolConfigService)

    public function isToolEnabled(string $tool): bool
    {
        return $this->toolConfigService?->isToolEnabled($this->data, $tool)
            ?? true;
    }

    public function getToolConfig(string $tool): array
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];
        $config = $toolsConfig[$tool] ?? [];

        // Apply tool-specific defaults for backward compatibility (match EnhancedConfiguration)
        return match ($tool) {
            'phpstan' => $this->getPhpStanConfig($config),
            'rector' => $this->getRectorConfig($config),
            'fractor' => $this->getFractorConfig($config),
            'php-cs-fixer' => $this->getPhpCsFixerConfig($config),
            'typoscript-lint' => $this->getTypoScriptLintConfig($config),
            default => $config,
        };
    }

    // Output configuration methods

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

    // Performance configuration methods

    public function isParallelEnabled(): bool
    {
        $qualityTools = $this->data['quality-tools'] ?? [];
        $performanceConfig = $qualityTools['performance'] ?? [];

        return $performanceConfig['parallel'] ?? false;
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

        return $performanceConfig['cache'] ?? true;
    }

    // Vendor directory methods (delegated to PathResolutionService)

    public function getVendorPath(): ?string
    {
        if ($this->pathResolutionService === null) {
            return null;
        }

        return $this->pathResolutionService->getVendorPath($this->projectRoot);
    }

    public function getVendorBinPath(): ?string
    {
        if ($this->pathResolutionService === null) {
            return null;
        }

        return $this->pathResolutionService->getVendorBinPath($this->projectRoot);
    }

    public function hasVendorDirectory(): bool
    {
        return $this->getVendorPath() !== null;
    }

    public function getVendorDetectionDebugInfo(): array
    {
        return [
            'project_root' => $this->projectRoot,
            'vendor_path' => $this->getVendorPath(),
            'vendor_bin_path' => $this->getVendorBinPath(),
            'detection_method' => $this->pathResolutionService !== null ? 'PathResolutionService' : 'Not available',
        ];
    }

    // Path resolution methods (delegated to PathResolutionService)

    public function getResolvedPathsForTool(string $tool): array
    {
        if ($this->pathResolutionService !== null) {
            return $this->pathResolutionService->getResolvedPathsForTool($this->data, $tool, $this->projectRoot);
        }

        return $this->getScanPaths();
    }

    public function getPathScanningDebugInfo(string $tool): array
    {
        return [
            'tool' => $tool,
            'project_root' => $this->projectRoot,
            'scan_paths' => $this->getScanPaths(),
            'exclude_paths' => $this->getExcludePaths(),
            'tool_paths' => $this->getToolPaths($tool),
            'resolved_paths' => $this->getResolvedPathsForTool($tool),
            'path_resolution_service' => $this->pathResolutionService !== null ? 'Available' : 'Not available',
        ];
    }

    // Enhanced configuration methods (only available in hierarchical mode)

    public function getConfigurationSource(string $keyPath): ?string
    {
        if (!$this->hierarchicalMode) {
            return null;
        }

        return $this->sourceMap[$keyPath] ?? null;
    }

    public function getConfigurationSources(): array
    {
        return $this->hierarchicalMode ? $this->sourceMap : [];
    }

    public function getConfigurationConflicts(): array
    {
        return $this->hierarchicalMode ? $this->conflicts : [];
    }

    public function hasConfigurationConflicts(): bool
    {
        return $this->hierarchicalMode && !empty($this->conflicts);
    }

    public function getConflictsForKey(string $keyPath): array
    {
        if (!$this->hierarchicalMode) {
            return [];
        }

        return array_filter($this->conflicts, fn ($conflict): bool => $conflict['key_path'] === $keyPath);
    }

    public function getMergeSummary(): array
    {
        return $this->hierarchicalMode ? $this->mergeSummary : [];
    }

    public function usesCustomConfigFile(string $tool): bool
    {
        if (!$this->hierarchicalMode) {
            return false;
        }

        $toolConfigKey = "quality-tools.tools.{$tool}.config_file";

        return isset($this->sourceMap[$toolConfigKey]);
    }

    public function getCustomConfigFilePath(string $tool): ?string
    {
        if (!$this->hierarchicalMode) {
            return null;
        }

        $toolConfig = $this->getToolConfig($tool);

        return $toolConfig['config_file'] ?? null;
    }

    public function getConfigurationWithSources(): array
    {
        if (!$this->hierarchicalMode) {
            return $this->data;
        }

        $result = [];
        foreach ($this->data as $key => $value) {
            $result[$key] = [
                'value' => $value,
                'source' => $this->sourceMap[$key] ?? 'unknown',
            ];
        }

        return $result;
    }

    public function getToolConfigurationResolved(string $tool): array
    {
        $toolConfig = $this->getToolConfig($tool);

        if (!$this->hierarchicalMode) {
            return $toolConfig;
        }

        // Add source information for hierarchical mode
        $resolved = [];
        $toolPrefix = "quality-tools.tools.{$tool}";

        foreach ($toolConfig as $key => $value) {
            $keyPath = "{$toolPrefix}.{$key}";
            $resolved[$key] = [
                'value' => $value,
                'source' => $this->sourceMap[$keyPath] ?? 'default',
                'overridden' => $this->wasValueOverridden($keyPath),
            ];
        }

        return $resolved;
    }

    public function getHierarchyInfo(): ?array
    {
        return $this->hierarchy?->getDebugInfo();
    }

    public function getDiscoveryInfo(): ?array
    {
        return $this->discovery?->getDiscoveryDebugInfo();
    }

    public function isHierarchicalConfiguration(): bool
    {
        return $this->hierarchicalMode;
    }

    public function getToolsWithCustomConfigs(): array
    {
        if (!$this->hierarchicalMode) {
            return [];
        }

        $qualityTools = $this->data['quality-tools'] ?? [];
        $toolsConfig = $qualityTools['tools'] ?? [];

        $toolsWithCustomConfigs = [];
        foreach ($toolsConfig as $tool => $config) {
            if (isset($config['config_file'])) {
                $toolsWithCustomConfigs[] = $tool;
            }
        }

        return $toolsWithCustomConfigs;
    }

    public function getComprehensiveDebugInfo(): array
    {
        return [
            'mode' => $this->hierarchicalMode ? 'hierarchical' : 'simple',
            'data_keys' => array_keys($this->data),
            'has_source_map' => !empty($this->sourceMap),
            'has_conflicts' => !empty($this->conflicts),
            'has_merge_summary' => !empty($this->mergeSummary),
            'project_root' => $this->projectRoot,
            'services' => [
                'project_config' => $this->projectConfigService !== null,
                'tool_config' => $this->toolConfigService !== null,
                'path_resolution' => $this->pathResolutionService !== null,
            ],
            'hierarchy_info' => $this->getHierarchyInfo(),
            'discovery_info' => $this->getDiscoveryInfo(),
            'vendor_info' => $this->getVendorDetectionDebugInfo(),
        ];
    }

    public function exportWithMetadata(): array
    {
        $export = [
            'configuration' => $this->data,
            'mode' => $this->hierarchicalMode ? 'hierarchical' : 'simple',
        ];

        if ($this->hierarchicalMode) {
            $export['metadata'] = [
                'source_map' => $this->sourceMap,
                'conflicts' => $this->conflicts,
                'merge_summary' => $this->mergeSummary,
                'hierarchy_info' => $this->getHierarchyInfo(),
                'discovery_info' => $this->getDiscoveryInfo(),
            ];
        }

        return $export;
    }

    public function wasValueOverridden(string $keyPath): bool
    {
        if (!$this->hierarchicalMode) {
            return false;
        }

        // Check if the value was set by multiple sources (indicating override)
        foreach ($this->conflicts as $conflict) {
            if ($conflict['key_path'] === $keyPath) {
                return true;
            }
        }

        // Check merge summary for override indicators
        foreach ($this->mergeSummary as $summary) {
            if (isset($summary['overrides'][$keyPath])) {
                return true;
            }
        }

        return false;
    }

    public function getConfigurationChain(string $keyPath): array
    {
        if (!$this->hierarchicalMode) {
            return [];
        }

        $chain = [];

        // Find all sources that provided this key
        foreach ($this->mergeSummary as $summary) {
            if (isset($summary['keys'][$keyPath])) {
                $chain[] = [
                    'source' => $summary['source'],
                    'value' => $summary['keys'][$keyPath],
                    'priority' => $summary['priority'] ?? 0,
                ];
            }
        }

        // Sort by priority (highest first)
        usort($chain, fn ($a, $b): int => $b['priority'] <=> $a['priority']);

        return $chain;
    }

    // Merge functionality

    public function merge(ConfigurationInterface $other): ConfigurationInterface
    {
        if (!$other instanceof self) {
            throw new \InvalidArgumentException('Can only merge with another Configuration instance');
        }

        $mergedData = array_merge_recursive($this->data, $other->data);
        $mergedSourceMap = array_merge($this->sourceMap, $other->sourceMap);
        $mergedConflicts = array_merge($this->conflicts, $other->conflicts);
        $mergedMergeSummary = array_merge($this->mergeSummary, $other->mergeSummary);

        // Determine if merged configuration should be hierarchical
        $hierarchicalMode = $this->hierarchicalMode || $other->hierarchicalMode;

        return new self(
            projectRoot: $this->projectRoot, // Use this configuration's project root
            data: $mergedData,
            sourceMap: $mergedSourceMap,
            conflicts: $mergedConflicts,
            mergeSummary: $mergedMergeSummary,
            hierarchicalMode: $hierarchicalMode,
            validator: $this->validator ?? $other->validator,
            projectConfigService: $this->projectConfigService ?? $other->projectConfigService,
            toolConfigService: $this->toolConfigService ?? $other->toolConfigService,
            pathResolutionService: $this->pathResolutionService ?? $other->pathResolutionService,
            hierarchy: $this->hierarchy ?? $other->hierarchy,
            discovery: $this->discovery ?? $other->discovery,
        );
    }

    // Factory methods for creating configurations

    public static function createDefault(string $projectRoot): self
    {
        return new self(
            projectRoot: $projectRoot,
            data: self::DEFAULT_CONFIGURATION,
        );
    }

    public static function createSimple(
        string $projectRoot,
        array $data = [],
        ?ConfigurationValidator $validator = null,
        ?ProjectConfigService $projectConfigService = null,
        ?ToolConfigService $toolConfigService = null,
        ?PathResolutionService $pathResolutionService = null,
    ): self {
        return new self(
            projectRoot: $projectRoot,
            data: $data,
            hierarchicalMode: false,
            validator: $validator,
            projectConfigService: $projectConfigService ?? self::createDefaultProjectConfigService(),
            toolConfigService: $toolConfigService ?? self::createDefaultToolConfigService(),
            pathResolutionService: $pathResolutionService ?? self::createDefaultPathResolutionService(),
        );
    }

    public static function createHierarchical(
        string $projectRoot,
        array $data = [],
        array $sourceMap = [],
        array $conflicts = [],
        array $mergeSummary = [],
        ?ConfigurationHierarchy $hierarchy = null,
        ?ConfigurationDiscovery $discovery = null,
        ?ConfigurationValidator $validator = null,
        ?ProjectConfigService $projectConfigService = null,
        ?ToolConfigService $toolConfigService = null,
        ?PathResolutionService $pathResolutionService = null,
    ): self {
        return new self(
            projectRoot: $projectRoot,
            data: $data,
            sourceMap: $sourceMap,
            conflicts: $conflicts,
            mergeSummary: $mergeSummary,
            hierarchicalMode: true,
            validator: $validator,
            projectConfigService: $projectConfigService ?? self::createDefaultProjectConfigService(),
            toolConfigService: $toolConfigService ?? self::createDefaultToolConfigService(),
            pathResolutionService: $pathResolutionService ?? self::createDefaultPathResolutionService(),
            hierarchy: $hierarchy,
            discovery: $discovery,
        );
    }

    private static function createDefaultProjectConfigService(): ProjectConfigService
    {
        return new ProjectConfigService();
    }

    private static function createDefaultToolConfigService(): ToolConfigService
    {
        return new ToolConfigService();
    }

    private static function createDefaultPathResolutionService(): PathResolutionService
    {
        $securityService = new SecurityService();
        $filesystem = new Filesystem();
        $filesystemService = new FilesystemService($filesystem, $securityService);
        $vendorDirectoryDetector = new VendorDirectoryDetector();

        return new PathResolutionService($filesystemService, $vendorDirectoryDetector);
    }

    // Tool-specific configuration methods (use ConfigurationInterface defaults)

    private function getPhpStanConfig(array $config = []): array
    {
        $defaults = self::DEFAULT_CONFIGURATION['quality-tools']['tools']['phpstan'];

        return array_merge($defaults, $config);
    }

    private function getRectorConfig(array $config = []): array
    {
        $defaults = self::DEFAULT_CONFIGURATION['quality-tools']['tools']['rector'];
        // Add dynamic php_version for backward compatibility
        $defaults['php_version'] = $this->getProjectPhpVersion();

        return array_merge($defaults, $config);
    }

    private function getFractorConfig(array $config = []): array
    {
        $defaults = self::DEFAULT_CONFIGURATION['quality-tools']['tools']['fractor'];

        return array_merge($defaults, $config);
    }

    private function getPhpCsFixerConfig(array $config = []): array
    {
        $defaults = self::DEFAULT_CONFIGURATION['quality-tools']['tools']['php-cs-fixer'];

        return array_merge($defaults, $config);
    }

    private function getTypoScriptLintConfig(array $config = []): array
    {
        $defaults = self::DEFAULT_CONFIGURATION['quality-tools']['tools']['typoscript-lint'];

        return array_merge($defaults, $config);
    }
}
