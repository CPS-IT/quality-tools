<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\PathResolutionService;
use Cpsit\QualityTools\Service\ProjectConfigService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Service\ToolConfigService;
use Cpsit\QualityTools\Traits\ConfigurationFileReaderTrait;
use Cpsit\QualityTools\Traits\EnvironmentVariableInterpolationTrait;
use Cpsit\QualityTools\Traits\YamlFileLoaderTrait;

/**
 * Unified configuration loader supporting both simple and hierarchical loading modes.
 *
 * This class replaces both SimpleConfigurationLoader and HierarchicalConfigurationLoader,
 * providing all functionality through a single, unified interface.
 */
final readonly class ConfigurationLoader implements ConfigurationLoaderInterface
{
    use ConfigurationFileReaderTrait;
    use EnvironmentVariableInterpolationTrait;
    use YamlFileLoaderTrait;

    private const array CONFIG_FILES = [
        '.quality-tools.yaml',
        'quality-tools.yaml',
        'quality-tools.yml',
    ];

    public function __construct(
        private ConfigurationValidator $validator,
        private SecurityService $securityService,
        private FilesystemService $filesystemService,
        private ?ProjectConfigService $projectConfigService = null,
        private ?ToolConfigService $toolConfigService = null,
        private ?PathResolutionService $pathResolutionService = null,
    ) {
    }

    // Basic loading methods

    public function load(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        return $this->loadWithMode($projectRoot, $commandLineOverrides, false);
    }

    public function loadHierarchical(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        return $this->loadWithMode($projectRoot, $commandLineOverrides, true);
    }

    public function loadSimple(string $projectRoot, array $commandLineOverrides = []): ConfigurationInterface
    {
        return $this->loadWithMode($projectRoot, $commandLineOverrides, false);
    }

    /**
     * Load configuration with specified mode (simple or hierarchical).
     */
    private function loadWithMode(string $projectRoot, array $commandLineOverrides, bool $hierarchical): ConfigurationInterface
    {
        if ($hierarchical) {
            return $this->loadWithHierarchy($projectRoot, $commandLineOverrides);
        }

        return $this->loadWithoutHierarchy($projectRoot, $commandLineOverrides);
    }

    /**
     * Load configuration without hierarchy (simple mode).
     */
    private function loadWithoutHierarchy(string $projectRoot, array $commandLineOverrides): ConfigurationInterface
    {
        $configData = $this->loadConfigurationHierarchy($projectRoot);

        // Merge command line overrides
        if (!empty($commandLineOverrides)) {
            $configData = $this->deepMerge($configData, $commandLineOverrides);
        }

        // Validate final merged configuration to match wrapper behavior
        $this->validateMergedConfiguration($configData);

        $configuration = Configuration::createSimple(
            data: $configData,
            validator: $this->validator,
            projectConfigService: $this->projectConfigService,
            toolConfigService: $this->toolConfigService,
            pathResolutionService: $this->pathResolutionService,
        );

        $configuration->setProjectRoot($projectRoot);

        // Return wrapped instance to maintain compatibility
        return new ConfigurationWrapper($configuration, 'simple');
    }

    /**
     * Load configuration with full hierarchy support (hierarchical mode).
     */
    private function loadWithHierarchy(string $projectRoot, array $commandLineOverrides): ConfigurationInterface
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

        // Create hierarchical configuration with full metadata
        $configuration = Configuration::createHierarchical(
            data: $mergeResult['data'],
            sourceMap: $mergeResult['source_map'],
            conflicts: $mergeResult['conflicts'],
            mergeSummary: $mergeResult['merge_summary'],
            validator: $this->validator,
            projectConfigService: $this->projectConfigService,
            toolConfigService: $this->toolConfigService,
            pathResolutionService: $this->pathResolutionService,
            hierarchy: $hierarchy,
            discovery: $discovery,
        );

        $configuration->setProjectRoot($projectRoot);

        // Return wrapped instance to maintain compatibility
        return new ConfigurationWrapper($configuration, 'enhanced');
    }

    // Configuration discovery methods

    public function findConfigurationFile(string $projectRoot): ?string
    {
        foreach (self::CONFIG_FILES as $configFile) {
            $configPath = $projectRoot . '/' . $configFile;
            if (file_exists($configPath)) {
                return $configPath;
            }
        }

        return null;
    }

    public function supportsConfiguration(string $projectRoot): bool
    {
        return $this->findConfigurationFile($projectRoot) !== null;
    }

    // Tool-specific loading

    public function loadForTool(string $projectRoot, string $tool, array $commandLineOverrides = []): ConfigurationInterface
    {
        // For tool-specific loading, we use hierarchical mode to get full metadata
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

        // Merge configurations with tool-specific precedence
        $merger = new ConfigurationMerger();
        $mergeResult = $merger->mergeConfigurations($configurations);

        // Validate merged configuration
        $this->validateMergedConfiguration($mergeResult['data']);

        // Create tool-specific hierarchical configuration
        $configuration = Configuration::createHierarchical(
            data: $mergeResult['data'],
            sourceMap: $mergeResult['source_map'],
            conflicts: $mergeResult['conflicts'],
            mergeSummary: $mergeResult['merge_summary'],
            validator: $this->validator,
            projectConfigService: $this->projectConfigService,
            toolConfigService: $this->toolConfigService,
            pathResolutionService: $this->pathResolutionService,
            hierarchy: $hierarchy,
            discovery: $discovery,
        );

        $configuration->setProjectRoot($projectRoot);

        return $configuration;
    }

    // Configuration analysis methods

    public function hasHierarchicalConfiguration(string $projectRoot): bool
    {
        try {
            $hierarchy = new ConfigurationHierarchy($projectRoot);
            $discovery = new ConfigurationDiscovery(
                $hierarchy,
                $this->filesystemService,
                $this->securityService,
                $this->validator,
            );

            $configurations = $discovery->discoverConfigurations();

            // Has hierarchical configuration if multiple sources found
            return \count($configurations) > 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getConfigurationErrors(string $projectRoot): array
    {
        try {
            $hierarchy = new ConfigurationHierarchy($projectRoot);
            $discovery = new ConfigurationDiscovery(
                $hierarchy,
                $this->filesystemService,
                $this->securityService,
                $this->validator,
            );

            // Try to discover configurations to collect errors
            $discovery->discoverConfigurations();

            return $discovery->getConfigurationErrors();
        } catch (\Throwable $e) {
            return ['discovery_error' => $e->getMessage()];
        }
    }

    public function getConfigurationDebugInfo(string $projectRoot): array
    {
        $debugInfo = [
            'project_root' => $projectRoot,
            'configuration_file' => $this->findConfigurationFile($projectRoot),
            'supports_configuration' => $this->supportsConfiguration($projectRoot),
            'has_hierarchical' => $this->hasHierarchicalConfiguration($projectRoot),
            'errors' => $this->getConfigurationErrors($projectRoot),
        ];

        try {
            $hierarchy = new ConfigurationHierarchy($projectRoot);
            $discovery = new ConfigurationDiscovery(
                $hierarchy,
                $this->filesystemService,
                $this->securityService,
                $this->validator,
            );

            $debugInfo['hierarchy_info'] = $hierarchy->getDebugInfo();
            $debugInfo['discovery_info'] = $discovery->getDiscoveryDebugInfo();
            $debugInfo['sources'] = $this->getConfigurationSources($projectRoot);
        } catch (\Throwable $e) {
            $debugInfo['hierarchy_error'] = $e->getMessage();
        }

        return $debugInfo;
    }

    public function getConfigurationSources(string $projectRoot): array
    {
        try {
            $hierarchy = new ConfigurationHierarchy($projectRoot);
            $discovery = new ConfigurationDiscovery(
                $hierarchy,
                $this->filesystemService,
                $this->securityService,
                $this->validator,
            );

            $configurations = $discovery->discoverConfigurations();

            return array_map(static fn ($config) => [
                'source' => $config['source'],
                'file_path' => $config['file_path'],
                'file_type' => $config['file_type'],
                'tool' => $config['tool'] ?? null,
                'precedence' => $config['precedence'],
                'exists' => $config['file_path'] ? file_exists($config['file_path']) : true,
            ], $configurations);
        } catch (\Throwable $e) {
            return [['error' => $e->getMessage()]];
        }
    }

    // Preview methods

    public function previewMergedConfiguration(string $projectRoot, array $commandLineOverrides = []): array
    {
        try {
            $configuration = $this->loadWithHierarchy($projectRoot, $commandLineOverrides);

            return [
                'merged_data' => $configuration->toArray(),
                'source_map' => $configuration->getConfigurationSources(),
                'conflicts' => $configuration->getConfigurationConflicts(),
                'merge_summary' => $configuration->getMergeSummary(),
                'hierarchy_info' => $configuration->getHierarchyInfo(),
                'discovery_info' => $configuration->getDiscoveryInfo(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    // Backward compatibility methods

    public function createSimpleConfiguration(string $projectRoot): ConfigurationInterface
    {
        return $this->loadSimple($projectRoot);
    }

    // Private helper methods (from SimpleConfigurationLoader)

    private function loadConfigurationHierarchy(string $projectRoot): array
    {
        $configurations = [];

        // 1. Package defaults (lowest priority) - load from new unified Configuration
        $defaultData = $this->getDefaultConfiguration();
        if (!empty($defaultData)) {
            $configurations[] = $defaultData;
        }

        // 2. Global user configuration
        $globalConfig = $this->loadGlobalConfiguration();
        if (!empty($globalConfig)) {
            $configurations[] = $globalConfig;
        }

        // 3. Project-specific configuration (highest priority)
        $projectConfig = $this->loadProjectConfiguration($projectRoot);
        if (!empty($projectConfig)) {
            $configurations[] = $projectConfig;
        }

        // Merge configurations with precedence
        return $this->mergeConfigurations($configurations);
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'quality-tools' => [
                'project' => [
                    'php_version' => '8.3',
                    'typo3_version' => '13.4',
                ],
                'paths' => [
                    'scan' => ['packages/', 'config/system/'],
                    'exclude' => [
                        'var/',
                        'vendor/',
                        'public/',
                        '_assets/',
                        'fileadmin/',
                        'typo3/',
                        'Tests/',
                        'tests/',
                        'typo3conf/',
                    ],
                ],
                'tools' => [],
                'output' => [
                    'verbosity' => 'normal',
                    'colors' => true,
                    'progress' => true,
                ],
                'performance' => [
                    'parallel' => false,
                    'max_processes' => 4,
                    'cache_enabled' => true,
                ],
            ],
        ];
    }

    private function loadGlobalConfiguration(): array
    {
        $homeDir = getenv('HOME') ?: ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '');
        if (empty($homeDir)) {
            return [];
        }

        $globalConfigPath = $homeDir . '/.quality-tools.yaml';
        if (!file_exists($globalConfigPath)) {
            return [];
        }

        try {
            return $this->loadYamlFile($globalConfigPath);
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadProjectConfiguration(string $projectRoot): array
    {
        foreach (self::CONFIG_FILES as $configFile) {
            $configPath = $projectRoot . '/' . $configFile;
            if (file_exists($configPath)) {
                try {
                    return $this->loadYamlFile($configPath);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return [];
    }

    private function mergeConfigurations(array $configurations): array
    {
        $merged = [];

        foreach ($configurations as $config) {
            $merged = $this->deepMerge($merged, $config);
        }

        return $merged;
    }

    private function deepMerge(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (\is_array($value) && isset($array1[$key]) && \is_array($array1[$key])) {
                $array1[$key] = $this->deepMerge($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

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

    // Factory methods for creating loaders with specific modes

    public static function createSimpleLoader(
        ConfigurationValidator $validator,
        SecurityService $securityService,
        FilesystemService $filesystemService,
        ?ProjectConfigService $projectConfigService = null,
        ?ToolConfigService $toolConfigService = null,
        ?PathResolutionService $pathResolutionService = null,
    ): self {
        return new self(
            validator: $validator,
            securityService: $securityService,
            filesystemService: $filesystemService,
            projectConfigService: $projectConfigService,
            toolConfigService: $toolConfigService,
            pathResolutionService: $pathResolutionService,
        );
    }

    public static function createHierarchicalLoader(
        ConfigurationValidator $validator,
        SecurityService $securityService,
        FilesystemService $filesystemService,
        ?ProjectConfigService $projectConfigService = null,
        ?ToolConfigService $toolConfigService = null,
        ?PathResolutionService $pathResolutionService = null,
    ): self {
        return new self(
            validator: $validator,
            securityService: $securityService,
            filesystemService: $filesystemService,
            projectConfigService: $projectConfigService,
            toolConfigService: $toolConfigService,
            pathResolutionService: $pathResolutionService,
        );
    }
}
