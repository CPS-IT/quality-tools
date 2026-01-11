<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Traits\ConfigurationFileReaderTrait;
use Cpsit\QualityTools\Traits\EnvironmentVariableInterpolationTrait;
use Cpsit\QualityTools\Traits\YamlFileLoaderTrait;

/**
 * Discovers and loads configuration files from multiple locations.
 *
 * Implements the configuration file discovery mechanism for Feature 015.
 */
final class ConfigurationDiscovery
{
    use ConfigurationFileReaderTrait;
    use EnvironmentVariableInterpolationTrait;
    use YamlFileLoaderTrait;

    private array $configurationErrors = [];

    public function __construct(
        private readonly ConfigurationHierarchy $hierarchy,
        private readonly FilesystemService $filesystemService,
        private readonly SecurityService $securityService,
        private readonly ConfigurationValidator $validator,
    ) {
    }

    /**
     * Get configuration loading errors that occurred during discovery.
     *
     * @return array<string, string> Array of file paths mapped to error messages
     */
    public function getConfigurationErrors(): array
    {
        return $this->configurationErrors;
    }

    /**
     * Clear stored configuration errors.
     */
    public function clearConfigurationErrors(): void
    {
        $this->configurationErrors = [];
    }

    /**
     * Discover all configuration sources with their metadata.
     */
    public function discoverConfigurations(): array
    {
        $configurations = [];

        // 1. Package defaults (always available)
        $configurations[] = $this->createConfigurationSource(
            'package_defaults',
            null,
            SimpleConfiguration::createDefault()->toArray(),
        );

        // 2. Global user configuration
        $globalConfig = $this->discoverGlobalConfiguration();
        if ($globalConfig !== null) {
            $configurations[] = $globalConfig;
        }

        // 3. Project-level configurations
        $projectConfigurations = $this->discoverProjectConfigurations();
        $configurations = array_merge($configurations, $projectConfigurations);

        // Sort by precedence (the highest priority first)
        usort($configurations, fn (array $a, array $b): int => $this->hierarchy->getPrecedenceLevel($a['source']) <=>
               $this->hierarchy->getPrecedenceLevel($b['source']));

        return $configurations;
    }

    /**
     * Discover global user configuration.
     */
    private function discoverGlobalConfiguration(): ?array
    {
        $homeDir = $this->getHomeDirectory();
        if ($homeDir === null) {
            return null;
        }

        $globalConfigPath = $homeDir . '/.quality-tools.yaml';
        if (!file_exists($globalConfigPath)) {
            return null;
        }

        try {
            $data = $this->loadYamlFile($globalConfigPath);

            return $this->createConfigurationSource(
                'global',
                $globalConfigPath,
                $data,
            );
        } catch (ConfigurationLoadException) {
            // Skip invalid global configuration
            return null;
        }
    }

    /**
     * Discover all project-level configurations.
     */
    private function discoverProjectConfigurations(): array
    {
        $configurations = [];
        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        foreach ($existingFiles as $level => $files) {
            foreach ($files as $fileInfo) {
                try {
                    $data = $this->loadConfigurationFile($fileInfo);

                    $configurations[] = $this->createConfigurationSource(
                        $level,
                        $fileInfo['path'],
                        $data,
                        $fileInfo['type'],
                        $fileInfo['tool'],
                    );
                } catch (ConfigurationLoadException $e) {
                    // Store failed configuration for potential error reporting at the command level
                    $this->configurationErrors[$fileInfo['path']] = $e->getMessage();
                    continue;
                }
            }
        }

        return $configurations;
    }

    /**
     * Load configuration data based on a file type.
     */
    private function loadConfigurationFile(array $fileInfo): array
    {
        return match ($fileInfo['type']) {
            'yaml' => $this->loadYamlFile($fileInfo['path']),
            'php' => $this->loadPhpFile($fileInfo['path']),
            'neon' => $this->loadNeonFile($fileInfo['path']),
            default => throw new ConfigurationLoadException("Unsupported configuration file type: {$fileInfo['type']}", $fileInfo['path'])
        };
    }

    /**
     * Load the PHP configuration file (for tools like Rector).
     */
    private function loadPhpFile(string $path): array
    {
        // PHP configuration files are tool-specific and don't follow our YAML schema
        // We just mark them as existing and let the tool handle them
        return [
            'tool_config_file' => $path,
            'custom_config' => true,
        ];
    }

    /**
     * Load the Neon configuration file (for PHPStan).
     */
    private function loadNeonFile(string $path): array
    {
        // Neon configuration files are tool-specific
        // We just mark them as existing and let PHPStan handle them
        return [
            'tool_config_file' => $path,
            'custom_config' => true,
        ];
    }

    /**
     * Create a configuration source array with metadata.
     */
    private function createConfigurationSource(
        string $source,
        ?string $filePath,
        array $data,
        string $fileType = 'yaml',
        ?string $tool = null,
    ): array {
        return [
            'source' => $source,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'tool' => $tool,
            'data' => $data,
            'precedence' => $this->hierarchy->getPrecedenceLevel($source),
            'timestamp' => $filePath !== null && file_exists($filePath) ? filemtime($filePath) : time(),
        ];
    }

    /**
     * Get home directory for global configuration.
     */
    private function getHomeDirectory(): ?string
    {
        $homeDir = getenv('HOME') ?: ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '');

        return !empty($homeDir) ? $homeDir : null;
    }

    /**
     * Check if a configuration file exists for a specific tool.
     */
    public function hasToolConfiguration(string $tool): bool
    {
        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        foreach ($existingFiles as $files) {
            foreach ($files as $fileInfo) {
                if ($fileInfo['tool'] === $tool) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the path to a tool's configuration file.
     */
    public function getToolConfigurationPath(string $tool): ?string
    {
        $existingFiles = $this->hierarchy->getExistingConfigurationFiles();

        // Look for tool-specific configs in order of precedence
        foreach (ConfigurationHierarchy::PRECEDENCE_LEVELS as $level) {
            if (!isset($existingFiles[$level])) {
                continue;
            }

            foreach ($existingFiles[$level] as $fileInfo) {
                if ($fileInfo['tool'] === $tool) {
                    return $fileInfo['path'];
                }
            }
        }

        return null;
    }

    /**
     * Get all configuration files that would affect a specific tool.
     */
    public function getToolAffectingConfigurations(string $tool): array
    {
        $configurations = $this->discoverConfigurations();
        $affecting = [];

        foreach ($configurations as $config) {
            // Include general configurations and tool-specific configurations
            if ($config['tool'] === null || $config['tool'] === $tool) {
                $affecting[] = $config;
            }
        }

        return $affecting;
    }

    /**
     * Get debug information about configuration discovery.
     */
    public function getDiscoveryDebugInfo(): array
    {
        $configurations = $this->discoverConfigurations();

        return [
            'total_configurations_found' => \count($configurations),
            'configurations' => $configurations,
            'hierarchy_debug' => $this->hierarchy->getDebugInfo(),
            'global_config_path' => $this->getHomeDirectory() ? $this->getHomeDirectory() . '/.quality-tools.yaml' : null,
            'existing_files_by_level' => $this->hierarchy->getExistingConfigurationFiles(),
        ];
    }
}
