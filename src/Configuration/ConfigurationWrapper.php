<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ToolConfigService;

/**
 * Wrapper class that unifies SimpleConfiguration and EnhancedConfiguration.
 *
 * This wrapper implements the complete ConfigurationInterface by delegating
 * to the wrapped instance and handling missing methods gracefully.
 * Updated in Step 4.2 to use extracted business logic services.
 * Part of the evolutionary refactoring strategy in Issue 019.
 * @deprecated use Configuration instead
 */
final readonly class ConfigurationWrapper implements ConfigurationInterface
{
    public function __construct(
        private ConfigurationInterface $wrapped,
        private string $variant = 'simple',
        private ?ToolConfigService $toolConfigService = null,
        private ?PathResolutionService $pathResolutionService = null,
    ) {
        trigger_error(self::class . ' is deprecated, use Configuration instead.', E_USER_DEPRECATED);
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
        // Use service if available, otherwise delegate to wrapped instance
        if ($this->toolConfigService !== null) {
            return $this->toolConfigService->getRectorConfig($this->wrapped->toArray(), $this->wrapped->getProjectPhpVersion());
        }

        return $this->wrapped->getToolConfig('rector');
    }

    public function getFractorConfig(): array
    {
        // Use service if available, otherwise delegate to wrapped instance
        if ($this->toolConfigService !== null) {
            return $this->toolConfigService->getFractorConfig($this->wrapped->toArray());
        }

        return $this->wrapped->getToolConfig('fractor');
    }

    public function getPhpStanConfig(): array
    {
        // Use service if available, otherwise delegate to wrapped instance
        if ($this->toolConfigService !== null) {
            return $this->toolConfigService->getPhpStanConfig($this->wrapped->toArray());
        }

        return $this->wrapped->getToolConfig('phpstan');
    }

    public function getPhpCsFixerConfig(): array
    {
        // Use service if available, otherwise delegate to wrapped instance
        if ($this->toolConfigService !== null) {
            return $this->toolConfigService->getPhpCsFixerConfig($this->wrapped->toArray());
        }

        return $this->wrapped->getToolConfig('php-cs-fixer');
    }

    public function getTypoScriptLintConfig(): array
    {
        // Use service if available, otherwise delegate to wrapped instance
        if ($this->toolConfigService !== null) {
            return $this->toolConfigService->getTypoScriptLintConfig($this->wrapped->toArray());
        }

        return $this->wrapped->getToolConfig('typoscript-lint');
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

    // Path resolution methods (available in both variants)
    public function getPathScanningDebugInfo(string $tool): array
    {
        return $this->wrapped->getPathScanningDebugInfo($tool);
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
        return $this->wrapped instanceof EnhancedConfiguration && $this->wrapped->hasConfigurationConflicts();
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
        return $this->wrapped instanceof EnhancedConfiguration && $this->wrapped->usesCustomConfigFile($tool);
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
        return $this->wrapped instanceof EnhancedConfiguration && $this->wrapped->isHierarchicalConfiguration();
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
        return $this->wrapped instanceof EnhancedConfiguration && $this->wrapped->wasValueOverridden($keyPath);
    }

    public function getConfigurationChain(string $keyPath): array
    {
        return $this->wrapped instanceof EnhancedConfiguration
            ? $this->wrapped->getConfigurationChain($keyPath)
            : [];
    }

    // Path resolution methods (enhanced with PathResolutionService)
    public function getResolvedPathsForTool(string $tool): array
    {
        // Use service if available and we have a project root
        if ($this->pathResolutionService !== null && $this->wrapped->getProjectRoot() !== null) {
            return $this->pathResolutionService->getResolvedPathsForTool(
                $this->wrapped->toArray(),
                $tool,
                $this->wrapped->getProjectRoot(),
            );
        }

        // Fallback to wrapped instance (now available in both variants)
        return $this->wrapped->getResolvedPathsForTool($tool);
    }

    public function getTargetPathForTool(string $tool): string
    {
        $paths = $this->getResolvedPathsForTool($tool);

        return $paths[0] ?? '';
    }

    // Utility methods for wrapper introspection
    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getWrappedInstance(): ConfigurationInterface
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
