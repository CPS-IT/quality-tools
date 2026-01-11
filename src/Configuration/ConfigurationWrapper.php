<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Wrapper class that unifies SimpleConfiguration and EnhancedConfiguration.
 * 
 * This wrapper implements the complete ConfigurationInterface by delegating
 * to the wrapped instance and handling missing methods gracefully.
 * Part of the evolutionary refactoring strategy in Issue 019.
 */
final class ConfigurationWrapper implements ConfigurationInterface
{
    private SimpleConfiguration|EnhancedConfiguration $wrapped;
    private string $variant;

    public function __construct(
        SimpleConfiguration|EnhancedConfiguration $configuration,
        string $variant = 'simple'
    ) {
        $this->wrapped = $configuration;
        $this->variant = $variant;
    }

    // Core data access methods (available in both)
    public function toArray(): array
    {
        return $this->wrapped->toArray();
    }

    public function setProjectRoot(string $projectRoot): void
    {
        $this->wrapped->setProjectRoot($projectRoot);
    }

    public function getProjectRoot(): ?string
    {
        return $this->wrapped->getProjectRoot();
    }

    // Project configuration methods (available in both)
    public function getProjectPhpVersion(): string
    {
        return $this->wrapped->getProjectPhpVersion();
    }

    public function getProjectTypo3Version(): string
    {
        return $this->wrapped->getProjectTypo3Version();
    }

    public function getProjectName(): ?string
    {
        return $this->wrapped->getProjectName();
    }

    // Path configuration methods (available in both)
    public function getScanPaths(): array
    {
        return $this->wrapped->getScanPaths();
    }

    public function getExcludePaths(): array
    {
        return $this->wrapped->getExcludePaths();
    }

    public function getToolPaths(string $tool): array
    {
        return $this->wrapped->getToolPaths($tool);
    }

    // Tool configuration methods (available in both)
    public function isToolEnabled(string $tool): bool
    {
        return $this->wrapped->isToolEnabled($tool);
    }

    public function getToolConfig(string $tool): array
    {
        return $this->wrapped->getToolConfig($tool);
    }

    public function getRectorConfig(): array
    {
        return $this->wrapped->getRectorConfig();
    }

    public function getFractorConfig(): array
    {
        return $this->wrapped->getFractorConfig();
    }

    public function getPhpStanConfig(): array
    {
        return $this->wrapped->getPhpStanConfig();
    }

    public function getPhpCsFixerConfig(): array
    {
        return $this->wrapped->getPhpCsFixerConfig();
    }

    public function getTypoScriptLintConfig(): array
    {
        return $this->wrapped->getTypoScriptLintConfig();
    }

    // Output configuration methods (available in both)
    public function getVerbosity(): string
    {
        return $this->wrapped->getVerbosity();
    }

    public function isColorsEnabled(): bool
    {
        return $this->wrapped->isColorsEnabled();
    }

    public function isProgressEnabled(): bool
    {
        return $this->wrapped->isProgressEnabled();
    }

    // Performance configuration methods (available in both)
    public function isParallelEnabled(): bool
    {
        return $this->wrapped->isParallelEnabled();
    }

    public function getMaxProcesses(): int
    {
        return $this->wrapped->getMaxProcesses();
    }

    public function isCacheEnabled(): bool
    {
        return $this->wrapped->isCacheEnabled();
    }

    public function merge(ConfigurationInterface $other): ConfigurationInterface
    {
        return $this->wrapped->merge($other);
    }

    // Vendor directory methods (available in both)
    public function getVendorPath(): ?string
    {
        return $this->wrapped->getVendorPath();
    }

    public function getVendorBinPath(): ?string
    {
        return $this->wrapped->getVendorBinPath();
    }

    public function hasVendorDirectory(): bool
    {
        return $this->wrapped->hasVendorDirectory();
    }

    public function getVendorDetectionDebugInfo(): array
    {
        return $this->wrapped->getVendorDetectionDebugInfo();
    }

    // Path resolution methods (only available in SimpleConfiguration)
    public function getPathScanningDebugInfo(string $tool): array
    {
        return $this->wrapped instanceof SimpleConfiguration
            ? $this->wrapped->getPathScanningDebugInfo($tool)
            : [];
    }

    // Enhanced configuration methods (only available in EnhancedConfiguration)
    public function getConfigurationSource(string $keyPath): ?string
    {
        return $this->wrapped instanceof EnhancedConfiguration 
            ? $this->wrapped->getConfigurationSource($keyPath)
            : null;
    }

    public function getConfigurationSources(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConfigurationSources()
            : [];
    }

    public function getConfigurationConflicts(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConfigurationConflicts()
            : [];
    }

    public function hasConfigurationConflicts(): bool
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->hasConfigurationConflicts()
            : false;
    }

    public function getConflictsForKey(string $keyPath): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConflictsForKey($keyPath)
            : [];
    }

    public function getMergeSummary(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getMergeSummary()
            : [];
    }

    public function usesCustomConfigFile(string $tool): bool
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->usesCustomConfigFile($tool)
            : false;
    }

    public function getCustomConfigFilePath(string $tool): ?string
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getCustomConfigFilePath($tool)
            : null;
    }

    public function getConfigurationWithSources(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConfigurationWithSources()
            : [];
    }

    public function getToolConfigurationResolved(string $tool): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getToolConfigurationResolved($tool)
            : [];
    }

    public function getHierarchyInfo(): ?array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getHierarchyInfo()
            : null;
    }

    public function getDiscoveryInfo(): ?array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getDiscoveryInfo()
            : null;
    }

    public function isHierarchicalConfiguration(): bool
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->isHierarchicalConfiguration()
            : false;
    }

    public function getToolsWithCustomConfigs(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getToolsWithCustomConfigs()
            : [];
    }

    public function getComprehensiveDebugInfo(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getComprehensiveDebugInfo()
            : [];
    }

    public function exportWithMetadata(): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->exportWithMetadata()
            : [];
    }

    public function wasValueOverridden(string $keyPath): bool
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->wasValueOverridden($keyPath)
            : false;
    }

    public function getConfigurationChain(string $keyPath): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConfigurationChain($keyPath)
            : [];
    }


    // Path resolution methods (only available in SimpleConfiguration)
    public function getResolvedPathsForTool(string $tool): array
    {
        return $this->wrapped instanceof SimpleConfiguration
            ? $this->wrapped->getResolvedPathsForTool($tool)
            : [];
    }

    public function getTargetPathForTool(string $tool): string
    {
        if ($this->wrapped instanceof SimpleConfiguration) {
            $paths = $this->wrapped->getResolvedPathsForTool($tool);
            return $paths[0] ?? '';
        }
        return '';
    }

    // Utility methods for wrapper introspection
    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getWrappedInstance(): SimpleConfiguration|EnhancedConfiguration
    {
        return $this->wrapped;
    }

    public function isSimple(): bool
    {
        return $this->variant === 'simple' && $this->wrapped instanceof SimpleConfiguration;
    }

    public function isEnhanced(): bool
    {
        return $this->variant === 'enhanced' && $this->wrapped instanceof EnhancedConfiguration;
    }
}