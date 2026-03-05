<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigurationValidationService;

/**
 * Advanced configuration loader with hierarchical support and source tracking.
 *
 * Implements the complete configuration override system for Feature 015.
 *
 * @deprecated use ConfigurationLoader instead
 */
final readonly class HierarchicalConfigurationLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationValidator $validator,
        private SecurityService $securityService,
        private FilesystemService $filesystemService,
        private ToolConfigurationValidationService $toolValidator,
    ) {
        trigger_error(self::class . ' is deprecated, use ConfigurationLoader instead.', E_USER_DEPRECATED);
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
            $this->toolValidator,
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
            $this->toolValidator,
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
    private function validateMergedConfiguration(array &$data): void
    {
        if (empty($data)) {
            return; // Empty configuration is valid
        }

        // Normalize configuration structure before validation
        // Ensure 'tools' section is always treated as an object, never as an indexed array
        $this->normalizeConfigurationStructure($data);

        $validationResult = $this->validator->validateSafe($data);
        if (!$validationResult->isValid()) {
            $errors = implode("\n", $validationResult->getErrors());
            throw new ConfigurationLoadException("Invalid merged configuration:\n$errors", 'merged');
        }
    }

    /**
     * Fix Issue 022 Phase 2 Step 6: Normalize configuration structure to fix array/object type mismatches.
     *
     * Ensures that the 'tools' section is always treated as an associative array (object)
     * to prevent JSON schema validation errors.
     */
    private function normalizeConfigurationStructure(array &$data): void
    {
        if (!isset($data['quality-tools'])) {
            return;
        }

        // Fix tools section - ensure it's always an associative array (object), not indexed array
        if (isset($data['quality-tools']['tools'])) {
            $tools = &$data['quality-tools']['tools'];

            // If tools is an indexed array (has numeric consecutive keys starting from 0)
            if (\is_array($tools) && array_is_list($tools)) {
                // Convert indexed array to empty associative array
                // This fixes the "Array value found, but an object is required" error
                $tools = [];
            }

            // Ensure all tool entries are properly structured as associative arrays
            if (\is_array($tools)) {
                foreach ($tools as &$toolConfig) {
                    if (\is_array($toolConfig) && array_is_list($toolConfig)) {
                        $toolConfig = [];
                    }
                }
                unset($toolConfig); // Break reference
            }
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
            $this->toolValidator,
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
            $this->toolValidator,
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
            $this->toolValidator,
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
            $this->toolValidator,
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
