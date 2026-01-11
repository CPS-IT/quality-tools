<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;

/**
 * Advanced configuration loader with hierarchical support and source tracking.
 *
 * Implements the complete configuration override system for Feature 015.
 */
final readonly class HierarchicalConfigurationLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationValidator $validator,
        private SecurityService $securityService,
        private FilesystemService $filesystemService,
    ) {
    }

    /**
     * Load configuration with full hierarchy support and source tracking.
     */
    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );
        $merger = new ConfigurationMerger();

        // Discover all configuration sources
        $configurations = $discovery->discoverConfigurations();

        // Add command line overrides as highest priority
        if (!empty($commandLineOverrides)) {
            $configurations[] = [
                'source' => 'command_line',
                'file_path' => null,
                'file_type' => 'array',
                'tool' => null,
                'data' => $commandLineOverrides,
                'precedence' => -1, // Highest priority
                'timestamp' => time(),
            ];
        }

        // Merge all configurations
        $mergeResult = $merger->mergeConfigurations($configurations);

        // Validate final merged configuration
        $this->validateMergedConfiguration($mergeResult['data']);

        // Create enhanced configuration with full metadata
        $enhanced = new EnhancedConfiguration(
            data: $mergeResult['data'],
            sourceMap: $mergeResult['source_map'],
            conflicts: $mergeResult['conflicts'],
            mergeSummary: $mergeResult['merge_summary'],
            hierarchy: $hierarchy,
            discovery: $discovery,
            projectRoot: $projectRoot,
            validator: $this->validator,
        );

        return $enhanced;
    }

    /**
     * Load configuration for a specific tool with tool-specific precedence.
     */
    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );

        // Get configurations that affect this tool
        $configurations = $discovery->getToolAffectingConfigurations($tool);

        // If tool has its own config file, it overrides unified configurations
        if ($discovery->hasToolConfiguration($tool)) {
            $toolConfigPath = $discovery->getToolConfigurationPath($tool);
            if ($toolConfigPath !== null) {
                // Mark that this tool uses a custom config file
                $commandLineOverrides['quality-tools']['tools'][$tool]['config_file'] = $toolConfigPath;
                $commandLineOverrides['quality-tools']['tools'][$tool]['use_custom_config'] = true;
            }
        }

        // Add command line overrides
        if (!empty($commandLineOverrides)) {
            $configurations[] = [
                'source' => 'command_line',
                'file_path' => null,
                'file_type' => 'array',
                'tool' => $tool,
                'data' => $commandLineOverrides,
                'precedence' => -1,
                'timestamp' => time(),
            ];
        }

        $merger = new ConfigurationMerger();
        $mergeResult = $merger->mergeConfigurations($configurations);

        // Validate final configuration
        $this->validateMergedConfiguration($mergeResult['data']);

        return new EnhancedConfiguration(
            data: $mergeResult['data'],
            sourceMap: $mergeResult['source_map'],
            conflicts: $mergeResult['conflicts'],
            mergeSummary: $mergeResult['merge_summary'],
            hierarchy: $hierarchy,
            discovery: $discovery,
            projectRoot: $projectRoot,
            validator: $this->validator,
        );
    }

    /**
     * Validate the final merged configuration.
     */
    private function validateMergedConfiguration(array $data): void
    {
        if (empty($data)) {
            return; // Empty configuration is valid
        }

        $validationResult = $this->validator->validateSafe($data);
        if (!$validationResult->isValid()) {
            $errors = implode("\n", $validationResult->getErrors());
            throw new ConfigurationLoadException("Invalid merged configuration:\n$errors", 'merged');
        }
    }

    /**
     * Create a simple loader for backward compatibility.
     */
    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface
    {
        $enhanced = $this->load($projectRoot);

        return new SimpleConfiguration($enhanced->toArray(), $this->validator);
    }

    /**
     * Check if hierarchical configuration is available for a project.
     */
    public function hasHierarchicalConfiguration(string $projectRoot): bool
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $existingFiles = $hierarchy->getExistingConfigurationFiles();

        return !empty($existingFiles);
    }

    /**
     * Get configuration loading errors for diagnostic purposes.
     *
     * @return array<string, string> Array of file paths mapped to error messages
     */
    public function getConfigurationErrors(string $projectRoot): array
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );

        // Trigger discovery to collect errors
        $discovery->discoverConfigurations();

        return $discovery->getConfigurationErrors();
    }

    /**
     * Get debug information about configuration loading for a project.
     */
    public function getConfigurationDebugInfo(string $projectRoot): array
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );

        return [
            'project_root' => $projectRoot,
            'hierarchy_info' => $hierarchy->getDebugInfo(),
            'discovery_info' => $discovery->getDiscoveryDebugInfo(),
            'has_hierarchical_config' => $this->hasHierarchicalConfiguration($projectRoot),
        ];
    }

    /**
     * Preview what the merged configuration would look like without loading.
     */
    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );

        $configurations = $discovery->discoverConfigurations();

        if (!empty($commandLineOverrides)) {
            $configurations[] = [
                'source' => 'command_line',
                'file_path' => null,
                'file_type' => 'array',
                'tool' => null,
                'data' => $commandLineOverrides,
                'precedence' => -1,
                'timestamp' => time(),
            ];
        }

        $merger = new ConfigurationMerger();

        return $merger->mergeConfigurations($configurations);
    }

    /**
     * Get all configuration files that would be loaded for a project.
     */
    public function getConfigurationSources(string $projectRoot): array
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $discovery = new ConfigurationDiscovery(
            $hierarchy,
            $this->filesystemService,
            $this->securityService,
            $this->validator,
        );

        $configurations = $discovery->discoverConfigurations();
        $sources = [];

        foreach ($configurations as $config) {
            $sources[] = [
                'source' => $config['source'],
                'file_path' => $config['file_path'],
                'file_type' => $config['file_type'],
                'tool' => $config['tool'],
                'precedence' => $config['precedence'],
                'exists' => $config['file_path'] !== null ? file_exists($config['file_path']) : true,
                'readable' => $config['file_path'] !== null ? is_readable($config['file_path']) : true,
            ];
        }

        return $sources;
    }

    // ConfigurationLoaderInterface implementation - missing methods

    public function findConfigurationFile(string $projectRoot): ?string
    {
        $hierarchy = new ConfigurationHierarchy($projectRoot);
        $existingFiles = $hierarchy->getExistingConfigurationFiles();

        // Return the first project-level configuration file found
        if (isset($existingFiles['project_root'])) {
            foreach ($existingFiles['project_root'] as $fileInfo) {
                return $fileInfo['path'];
            }
        }

        return null;
    }

    public function supportsConfiguration(string $projectRoot): bool
    {
        return $this->hasHierarchicalConfiguration($projectRoot)
               || $this->findConfigurationFile($projectRoot) !== null;
    }
}
