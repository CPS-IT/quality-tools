<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Cpsit\QualityTools\Traits\ConfigurationFileReaderTrait;
use Cpsit\QualityTools\Traits\EnvironmentVariableInterpolationTrait;
use Cpsit\QualityTools\Traits\YamlFileLoaderTrait;

final readonly class YamlConfigurationLoader
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
    ) {
    }

    public function load(string $projectRoot): Configuration
    {
        $configData = $this->loadConfigurationHierarchy($projectRoot);
        $configuration = new Configuration($configData);
        $configuration->setProjectRoot($projectRoot);

        return $configuration;
    }

    private function loadConfigurationHierarchy(string $projectRoot): array
    {
        $configurations = [];

        // 1. Package defaults (lowest priority)
        $configurations[] = Configuration::createDefault()->toArray();

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

        return $this->loadYamlFile($globalConfigPath);
    }

    private function loadProjectConfiguration(string $projectRoot): array
    {
        foreach (self::CONFIG_FILES as $configFile) {
            $configPath = $projectRoot . '/' . $configFile;
            if (file_exists($configPath)) {
                return $this->loadYamlFile($configPath);
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
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key])) {
                // If both arrays are indexed (not associative), merge and deduplicate
                if ($this->isIndexedArray($value) && $this->isIndexedArray($merged[$key])) {
                    $merged[$key] = array_values(array_unique(array_merge($merged[$key], $value)));
                } else {
                    $merged[$key] = $this->deepMerge($merged[$key], $value);
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function isIndexedArray(array $array): bool
    {
        return array_is_list($array);
    }

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
}
