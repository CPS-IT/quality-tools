<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Wrapper class that unifies SimpleConfigurationLoader and HierarchicalConfigurationLoader.
 *
 * This wrapper implements the complete ConfigurationLoaderInterface by delegating
 * to the appropriate loader based on the configured mode.
 * Part of the evolutionary refactoring strategy in Issue 019.
 */
final readonly class ConfigurationLoaderWrapper implements ConfigurationLoaderInterface
{
    public function __construct(
        private SimpleConfigurationLoader $simpleLoader,
        private HierarchicalConfigurationLoader $hierarchicalLoader,
        private string $mode = 'simple',
    ) {
    }

    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        return match ($this->mode) {
            'simple' => new ConfigurationWrapper(
                $this->simpleLoader->load($projectRoot),
                'simple',
            ),
            'hierarchical' => new ConfigurationWrapper(
                $this->hierarchicalLoader->load($projectRoot, $commandLineOverrides),
                'enhanced',
            ),
            default => throw new \InvalidArgumentException("Unknown loader mode: {$this->mode}")
        };
    }

    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
    {
        return match ($this->mode) {
            'simple' => new ConfigurationWrapper(
                $this->simpleLoader->loadForTool($projectRoot, $tool, $commandLineOverrides),
                'simple',
            ),
            'hierarchical' => new ConfigurationWrapper(
                $this->hierarchicalLoader->loadForTool($projectRoot, $tool, $commandLineOverrides),
                'enhanced',
            ),
            default => throw new \InvalidArgumentException("Unknown loader mode: {$this->mode}")
        };
    }

    public function supportsConfiguration(string $projectRoot): bool
    {
        return match ($this->mode) {
            'simple' => $this->simpleLoader->supportsConfiguration($projectRoot),
            'hierarchical' => $this->hierarchicalLoader->supportsConfiguration($projectRoot),
            default => false
        };
    }

    public function findConfigurationFile(string $projectRoot): ?string
    {
        return match ($this->mode) {
            'simple' => $this->simpleLoader->findConfigurationFile($projectRoot),
            'hierarchical' => $this->hierarchicalLoader->findConfigurationFile($projectRoot),
            default => null
        };
    }


    public function hasHierarchicalConfiguration(string $projectRoot): bool
    {
        return match ($this->mode) {
            'simple' => false, // Simple loader doesn't support hierarchical
            'hierarchical' => $this->hierarchicalLoader->hasHierarchicalConfiguration($projectRoot),
            default => false
        };
    }

    public function getConfigurationErrors(string $projectRoot): array
    {
        return match ($this->mode) {
            'simple' => [], // Simple loader doesn't track errors separately
            'hierarchical' => $this->hierarchicalLoader->getConfigurationErrors($projectRoot),
            default => []
        };
    }

    public function getConfigurationDebugInfo(string $projectRoot): array
    {
        return match ($this->mode) {
            'simple' => [], // Simple loader doesn't provide debug info
            'hierarchical' => $this->hierarchicalLoader->getConfigurationDebugInfo($projectRoot),
            default => []
        };
    }

    public function getConfigurationSources(string $projectRoot): array
    {
        return match ($this->mode) {
            'simple' => [], // Simple loader doesn't track sources separately
            'hierarchical' => $this->hierarchicalLoader->getConfigurationSources($projectRoot),
            default => []
        };
    }

    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
    {
        return match ($this->mode) {
            'simple' => $this->simpleLoader->load($projectRoot)->toArray(), // Simple preview
            'hierarchical' => $this->hierarchicalLoader->previewMergedConfiguration($projectRoot, $commandLineOverrides),
            default => []
        };
    }

    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface
    {
        return match ($this->mode) {
            'simple' => new ConfigurationWrapper($this->simpleLoader->load($projectRoot), 'simple'),
            'hierarchical' => $this->hierarchicalLoader->createSimpleConfiguration($projectRoot),
            default => throw new \InvalidArgumentException("Unknown loader mode: {$this->mode}")
        };
    }

    // Utility methods for wrapper management
    public function getMode(): string
    {
        return $this->mode;
    }

    public function getSimpleLoader(): SimpleConfigurationLoader
    {
        return $this->simpleLoader;
    }

    public function getHierarchicalLoader(): HierarchicalConfigurationLoader
    {
        return $this->hierarchicalLoader;
    }

    public function isSimpleMode(): bool
    {
        return $this->mode === 'simple';
    }

    public function isHierarchicalMode(): bool
    {
        return $this->mode === 'hierarchical';
    }

    /**
     * Create a new wrapper with different mode.
     */
    public function withMode(string $mode): self
    {
        return new self($this->simpleLoader, $this->hierarchicalLoader, $mode);
    }

    /**
     * Create a wrapper configured for simple mode.
     */
    public static function createSimple(SimpleConfigurationLoader $simpleLoader, HierarchicalConfigurationLoader $hierarchicalLoader): self
    {
        return new self($simpleLoader, $hierarchicalLoader, 'simple');
    }

    /**
     * Create a wrapper configured for hierarchical mode.
     */
    public static function createHierarchical(SimpleConfigurationLoader $simpleLoader, HierarchicalConfigurationLoader $hierarchicalLoader): self
    {
        return new self($simpleLoader, $hierarchicalLoader, 'hierarchical');
    }
}
