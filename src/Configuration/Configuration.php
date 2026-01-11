<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\VendorDirectoryNotFoundException;
use Cpsit\QualityTools\Utility\PathScanner;
use Cpsit\QualityTools\Utility\VendorDirectoryDetector;

class Configuration implements ConfigurationInterface
{
    private array $projectConfig;
    private array $pathsConfig;
    private array $toolsConfig;
    private array $outputConfig;
    private array $performanceConfig;
    private ?string $projectRoot = null;
    private ?string $vendorPath = null;
    private ?VendorDirectoryDetector $vendorDetector = null;
    private ?PathScanner $pathScanner = null;

    public function __construct(
        private readonly array $data = [],
        private readonly ?ConfigurationValidator $validator = null,
    ) {
        $this->parseConfiguration();
    }

    private function parseConfiguration(): void
    {
        // Validate configuration if validator is provided and data is not empty
        if ($this->validator !== null && !empty($this->data)) {
            $this->validator->validate($this->data);
        }

        $qualityTools = $this->data['quality-tools'] ?? [];

        $this->projectConfig = $qualityTools['project'] ?? [];
        $this->pathsConfig = $qualityTools['paths'] ?? [];
        $this->toolsConfig = $qualityTools['tools'] ?? [];
        $this->outputConfig = $qualityTools['output'] ?? [];
        $this->performanceConfig = $qualityTools['performance'] ?? [];
    }

    public function getProjectPhpVersion(): string
    {
        return $this->projectConfig['php_version'] ?? '8.3';
    }

    public function getProjectTypo3Version(): string
    {
        return $this->projectConfig['typo3_version'] ?? '13.4';
    }

    public function getProjectName(): ?string
    {
        return $this->projectConfig['name'] ?? null;
    }

    public function getScanPaths(): array
    {
        return $this->pathsConfig['scan'] ?? ['packages/', 'config/system/'];
    }

    public function getExcludePaths(): array
    {
        return $this->pathsConfig['exclude'] ?? ['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'];
    }

    public function getToolPaths(string $tool): array
    {
        return $this->toolsConfig[$tool]['paths'] ?? [];
    }

    public function isToolEnabled(string $tool): bool
    {
        return $this->toolsConfig[$tool]['enabled'] ?? true;
    }

    public function getToolConfig(string $tool): array
    {
        return $this->toolsConfig[$tool] ?? [];
    }

    public function getRectorConfig(): array
    {
        $config = $this->getToolConfig('rector');

        return array_merge([
            'enabled' => true,
            'level' => 'typo3-13',
            'php_version' => $this->getProjectPhpVersion(),
        ], $config);
    }

    public function getFractorConfig(): array
    {
        $config = $this->getToolConfig('fractor');

        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    public function getPhpStanConfig(): array
    {
        $config = $this->getToolConfig('phpstan');

        return array_merge([
            'enabled' => true,
            'level' => 6,
            'memory_limit' => '1G',
        ], $config);
    }

    public function getPhpCsFixerConfig(): array
    {
        $config = $this->getToolConfig('php-cs-fixer');

        return array_merge([
            'enabled' => true,
            'preset' => 'typo3',
        ], $config);
    }

    public function getTypoScriptLintConfig(): array
    {
        $config = $this->getToolConfig('typoscript-lint');

        return array_merge([
            'enabled' => true,
            'indentation' => 2,
        ], $config);
    }

    public function getVerbosity(): string
    {
        return $this->outputConfig['verbosity'] ?? 'normal';
    }

    public function isColorsEnabled(): bool
    {
        return $this->outputConfig['colors'] ?? true;
    }

    public function isProgressEnabled(): bool
    {
        return $this->outputConfig['progress'] ?? true;
    }

    public function isParallelEnabled(): bool
    {
        return $this->performanceConfig['parallel'] ?? true;
    }

    public function getMaxProcesses(): int
    {
        return $this->performanceConfig['max_processes'] ?? 4;
    }

    public function isCacheEnabled(): bool
    {
        return $this->performanceConfig['cache_enabled'] ?? true;
    }

    public function setProjectRoot(string $projectRoot): void
    {
        $this->projectRoot = $projectRoot;
        $this->vendorPath = null; // Reset vendor path cache
        $this->pathScanner = null; // Reset path scanner
    }

    public function getProjectRoot(): ?string
    {
        return $this->projectRoot;
    }

    public function getVendorPath(): ?string
    {
        if ($this->vendorPath === null && $this->projectRoot !== null) {
            try {
                $detector = $this->getVendorDetector();
                $this->vendorPath = $detector->detectVendorPath($this->projectRoot);
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
        if ($this->projectRoot === null) {
            return ['error' => 'Project root not set'];
        }

        return $this->getVendorDetector()->getDetectionDebugInfo($this->projectRoot);
    }

    private function getVendorDetector(): VendorDirectoryDetector
    {
        if ($this->vendorDetector === null) {
            $this->vendorDetector = new VendorDirectoryDetector();
        }

        return $this->vendorDetector;
    }

    private function getPathScanner(): PathScanner
    {
        if ($this->pathScanner === null) {
            if ($this->projectRoot === null) {
                throw new \RuntimeException('Project root must be set before using path scanner');
            }

            $this->pathScanner = new PathScanner($this->projectRoot);
            $this->pathScanner->setVendorPath($this->getVendorPath());
        }

        return $this->pathScanner;
    }

    /**
     * Get all resolved paths for a specific tool (global scan paths + tool-specific paths - exclusions).
     */
    public function getResolvedPathsForTool(string $tool): array
    {
        if ($this->projectRoot === null) {
            return $this->getScanPaths(); // Fallback to standard paths
        }

        $scanner = $this->getPathScanner();

        // Start with global scan paths
        $globalScanPaths = $this->getScanPaths();

        // Get tool-specific paths
        $toolPaths = $this->getToolPaths($tool);
        $toolScanPaths = $toolPaths['scan'] ?? [];

        // Combine all scan patterns
        $allScanPatterns = array_merge($globalScanPaths, $toolScanPaths);

        // Get exclusion patterns and add them to scan patterns
        $globalExcludePaths = $this->getExcludePaths();
        $toolExcludePaths = $toolPaths['exclude'] ?? [];
        $allExcludePatterns = array_merge($globalExcludePaths, $toolExcludePaths);

        // Add exclusion patterns with '!' prefix to scan patterns
        if (!empty($allExcludePatterns)) {
            $exclusionPatterns = array_map(fn ($pattern): string => '!' . $pattern, $allExcludePatterns);
            $allScanPatterns = array_merge($allScanPatterns, $exclusionPatterns);
        }

        // Resolve all patterns (including exclusions) in one call
        $resolvedPaths = $scanner->resolvePaths($allScanPatterns);

        // Remove duplicates and sort
        $resolvedPaths = array_unique($resolvedPaths);
        sort($resolvedPaths);

        return $resolvedPaths;
    }

    /**
     * Get path scanning debug information.
     */
    public function getPathScanningDebugInfo(string $tool): array
    {
        if ($this->projectRoot === null) {
            return ['error' => 'Project root not set'];
        }

        $scanner = $this->getPathScanner();
        $globalScanPaths = $this->getScanPaths();
        $globalExcludePaths = $this->getExcludePaths();
        $toolPaths = $this->getToolPaths($tool);

        return [
            'tool' => $tool,
            'project_root' => $this->projectRoot,
            'vendor_path' => $this->getVendorPath(),
            'global_scan_paths' => $globalScanPaths,
            'global_exclude_paths' => $globalExcludePaths,
            'tool_paths' => $toolPaths,
            'resolved_paths' => $this->getResolvedPathsForTool($tool),
            'path_scanner_debug' => $scanner->getPathResolutionDebugInfo(
                array_merge($globalScanPaths, $toolPaths['scan'] ?? []),
            ),
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function merge(ConfigurationInterface $other): ConfigurationInterface
    {
        $mergedData = array_merge_recursive($this->data, $other->toArray());

        return new self($mergedData);
    }

    public static function createDefault(): self
    {
        return new self([
            'quality-tools' => [
                'project' => [
                    'php_version' => '8.3',
                    'typo3_version' => '13.4',
                ],
                'paths' => [
                    'scan' => ['packages/', 'config/system/'],
                    'exclude' => ['var/', 'vendor/', 'public/', '_assets/', 'fileadmin/', 'typo3/', 'Tests/', 'tests/', 'typo3conf/'],
                ],
                'tools' => [
                    'rector' => [
                        'enabled' => true,
                        'level' => 'typo3-13',
                    ],
                    'fractor' => [
                        'enabled' => true,
                        'indentation' => 2,
                    ],
                    'phpstan' => [
                        'enabled' => true,
                        'level' => 6,
                        'memory_limit' => '1G',
                    ],
                    'php-cs-fixer' => [
                        'enabled' => true,
                        'preset' => 'typo3',
                    ],
                    'typoscript-lint' => [
                        'enabled' => true,
                        'indentation' => 2,
                    ],
                ],
                'output' => [
                    'verbosity' => 'normal',
                    'colors' => true,
                    'progress' => true,
                ],
                'performance' => [
                    'parallel' => true,
                    'max_processes' => 4,
                    'cache_enabled' => true,
                ],
            ],
        ]);
    }

    /**
     * Create a configuration instance with validation enabled.
     *
     * @param array<string, mixed> $data Configuration data
     */
    public static function createWithValidation(array $data): self
    {
        return new self($data, new ConfigurationValidator());
    }

    // Enhanced configuration methods (return defaults for simple configuration)
    public function getConfigurationSource(string $keyPath): ?string
    {
        return null; // Simple configuration doesn't track sources
    }

    public function getConfigurationSources(): array
    {
        return []; // Simple configuration doesn't track sources
    }

    public function getConfigurationConflicts(): array
    {
        return []; // Simple configuration doesn't track conflicts
    }

    public function hasConfigurationConflicts(): bool
    {
        return false; // Simple configuration doesn't have conflicts
    }

    public function getConflictsForKey(string $keyPath): array
    {
        return []; // Simple configuration doesn't track conflicts
    }

    public function getMergeSummary(): array
    {
        return []; // Simple configuration doesn't track merge summary
    }

    public function usesCustomConfigFile(string $tool): bool
    {
        return false; // Simple configuration doesn't support custom config files
    }

    public function getCustomConfigFilePath(string $tool): ?string
    {
        return null; // Simple configuration doesn't support custom config files
    }

    public function getConfigurationWithSources(): array
    {
        return $this->toArray(); // Return data without source attribution
    }

    public function getToolConfigurationResolved(string $tool): array
    {
        return $this->getToolConfig($tool); // Simple resolution only
    }

    public function getHierarchyInfo(): ?array
    {
        return null; // Simple configuration doesn't have hierarchy
    }

    public function getDiscoveryInfo(): ?array
    {
        return null; // Simple configuration doesn't have discovery
    }

    public function isHierarchicalConfiguration(): bool
    {
        return false; // Simple configuration is not hierarchical
    }

    public function getToolsWithCustomConfigs(): array
    {
        return []; // Simple configuration doesn't support custom configs
    }

    public function getComprehensiveDebugInfo(): array
    {
        return [
            'type' => 'simple',
            'data_size' => count($this->data),
            'project_root' => $this->projectRoot,
            'vendor_path' => $this->getVendorPath(),
        ];
    }

    public function exportWithMetadata(): array
    {
        return [
            'configuration' => $this->toArray(),
            'metadata' => [
                'type' => 'simple',
                'hierarchical' => false,
            ],
        ];
    }

    public function wasValueOverridden(string $keyPath): bool
    {
        return false; // Simple configuration doesn't track overrides
    }

    public function getConfigurationChain(string $keyPath): array
    {
        return []; // Simple configuration doesn't track chains
    }
}
