<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

/**
 * Interface defining configuration loading methods.
 * 
 * Combines methods from both YamlConfigurationLoader and HierarchicalConfigurationLoader
 * to enable unified dependency injection during refactoring.
 */
interface ConfigurationLoaderInterface
{
    // Basic loading methods
    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface;
    
    // Configuration discovery methods
    public function findConfigurationFile(string $projectRoot): ?string;
    public function supportsConfiguration(string $projectRoot): bool;

    // Tool-specific loading (from HierarchicalConfigurationLoader)
    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface;

    // Configuration analysis methods (from HierarchicalConfigurationLoader)  
    public function hasHierarchicalConfiguration(string $projectRoot): bool;
    public function getConfigurationErrors(string $projectRoot): array;
    public function getConfigurationDebugInfo(string $projectRoot): array;
    public function getConfigurationSources(string $projectRoot): array;
    
    // Preview methods (from HierarchicalConfigurationLoader)
    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array;

    // Backward compatibility method (from HierarchicalConfigurationLoader)
    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface;
}