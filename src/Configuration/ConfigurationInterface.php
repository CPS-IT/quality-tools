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
