<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Interface defining all configuration access methods.
 *
 * Combines methods from both Configuration and EnhancedConfiguration classes
 * to enable unified dependency injection during refactoring.
 */
interface ConfigurationInterface
{
    // Default configuration values
    public const string DEFAULT_PHP_VERSION = '8.3';
    public const string DEFAULT_TYPO3_VERSION = '13.4';
    public const array DEFAULT_SCAN_PATHS = ['packages/', 'config/system/'];
    public const array DEFAULT_EXCLUDE_PATHS = [
        'var/',
        'vendor/',
        'public/',
        '_assets/',
        'fileadmin/',
        'typo3/',
        'Tests/',
        'tests/',
        'typo3conf/',
    ];

    // Tool configuration defaults
    public const string DEFAULT_RECTOR_LEVEL = 'typo3-14';
    public const array ALLOWED_RECTOR_LEVELS = ['typo3-11', 'typo3-12', 'typo3-13', 'typo3-14'];
    public const int DEFAULT_FRACTOR_INDENTATION = 2;
    public const int DEFAULT_PHPSTAN_LEVEL = 6;
    public const string DEFAULT_PHPSTAN_MEMORY_LIMIT = '1G';
    public const string DEFAULT_PHP_CS_FIXER_PRESET = 'typo3';
    public const int DEFAULT_TYPOSCRIPT_LINT_INDENTATION = 2;

    // Output configuration defaults
    public const string DEFAULT_VERBOSITY = 'normal';
    public const bool DEFAULT_COLORS_ENABLED = true;
    public const bool DEFAULT_PROGRESS_ENABLED = true;

    // Performance configuration defaults
    public const bool DEFAULT_PARALLEL_ENABLED = true;
    public const int DEFAULT_MAX_PROCESSES = 4;
    public const bool DEFAULT_CACHE_ENABLED = true;

    // Complete default configuration array
    public const array DEFAULT_CONFIGURATION = [
        'quality-tools' => [
            'project' => [
                'php_version' => self::DEFAULT_PHP_VERSION,
                'typo3_version' => self::DEFAULT_TYPO3_VERSION,
            ],
            'paths' => [
                'scan' => self::DEFAULT_SCAN_PATHS,
                'exclude' => self::DEFAULT_EXCLUDE_PATHS,
            ],
            'tools' => [
                'rector' => [
                    'enabled' => true,
                    'level' => self::DEFAULT_RECTOR_LEVEL,
                ],
                'fractor' => [
                    'enabled' => true,
                    'indentation' => self::DEFAULT_FRACTOR_INDENTATION,
                ],
                'phpstan' => [
                    'enabled' => true,
                    'level' => self::DEFAULT_PHPSTAN_LEVEL,
                    'memory_limit' => self::DEFAULT_PHPSTAN_MEMORY_LIMIT,
                ],
                'php-cs-fixer' => [
                    'enabled' => true,
                    'preset' => self::DEFAULT_PHP_CS_FIXER_PRESET,
                ],
                'typoscript-lint' => [
                    'enabled' => true,
                    'indentation' => self::DEFAULT_TYPOSCRIPT_LINT_INDENTATION,
                ],
            ],
            'output' => [
                'verbosity' => self::DEFAULT_VERBOSITY,
                'colors' => self::DEFAULT_COLORS_ENABLED,
                'progress' => self::DEFAULT_PROGRESS_ENABLED,
            ],
            'performance' => [
                'parallel' => self::DEFAULT_PARALLEL_ENABLED,
                'max_processes' => self::DEFAULT_MAX_PROCESSES,
                'cache_enabled' => self::DEFAULT_CACHE_ENABLED,
            ],
        ],
    ];

    // Core data access methods
    public function toArray(): array;

    public function setProjectRoot(string $projectRoot): void;

    public function getProjectRoot(): ?string;

    // Project configuration methods
    public function getProjectPhpVersion(): string;

    public function getProjectTypo3Version(): string;

    public function getProjectName(): ?string;

    // Path configuration methods
    public function getScanPaths(): array;

    public function getExcludePaths(): array;

    public function getToolPaths(string $tool): array;

    // Tool configuration methods
    public function isToolEnabled(string $tool): bool;

    public function getToolConfig(string $tool): array;

    // Output configuration methods
    public function getVerbosity(): string;

    public function isColorsEnabled(): bool;

    public function isProgressEnabled(): bool;

    // Performance configuration methods
    public function isParallelEnabled(): bool;

    public function getMaxProcesses(): int;

    public function isCacheEnabled(): bool;

    // Vendor directory methods
    public function getVendorPath(): ?string;

    public function getVendorBinPath(): ?string;

    public function hasVendorDirectory(): bool;

    public function getVendorDetectionDebugInfo(): array;

    // Path resolution methods (from Configuration only)
    public function getResolvedPathsForTool(string $tool): array;

    public function getPathScanningDebugInfo(string $tool): array;

    // Enhanced configuration methods (from EnhancedConfiguration only)
    public function getConfigurationSource(string $keyPath): ?string;

    public function getConfigurationSources(): array;

    public function getConfigurationConflicts(): array;

    public function hasConfigurationConflicts(): bool;

    public function getConflictsForKey(string $keyPath): array;

    public function getMergeSummary(): array;

    public function usesCustomConfigFile(string $tool): bool;

    public function getCustomConfigFilePath(string $tool): ?string;

    public function getConfigurationWithSources(): array;

    public function getToolConfigurationResolved(string $tool): array;

    public function getHierarchyInfo(): ?array;

    public function getDiscoveryInfo(): ?array;

    public function isHierarchicalConfiguration(): bool;

    public function getToolsWithCustomConfigs(): array;

    public function getComprehensiveDebugInfo(): array;

    public function exportWithMetadata(): array;

    public function wasValueOverridden(string $keyPath): bool;

    public function getConfigurationChain(string $keyPath): array;

    // Merge functionality (from Configuration only)
    public function merge(ConfigurationInterface $other): ConfigurationInterface;
}
