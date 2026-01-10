<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationFileNotFoundException;
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Service\FilesystemService;
use Cpsit\QualityTools\Service\SecurityService;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlConfigurationLoader
{
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

    private function readConfigurationFile(string $path): string
    {
        try {
            return $this->filesystemService->readFile($path);
        } catch (FileSystemException $e) {
            // Convert filesystem exceptions to configuration-specific exceptions
            if ($e->getCode() === FileSystemException::ERROR_FILE_NOT_FOUND) {
                throw new ConfigurationFileNotFoundException($path);
            }
            if ($e->getCode() === FileSystemException::ERROR_FILE_NOT_READABLE) {
                throw new ConfigurationFileNotReadableException($path);
            }
            throw new ConfigurationLoadException('Failed to read configuration file: ' . $e->getMessage(), $path, $e);
        }
    }

    private function loadYamlFile(string $path): array
    {
        try {
            $content = $this->readConfigurationFile($path);

            // Interpolate environment variables
            $content = $this->interpolateEnvironmentVariables($content);

            // Parse YAML
            $data = Yaml::parse($content);
            if (!\is_array($data)) {
                throw new ConfigurationLoadException('Configuration file must contain valid YAML data', $path);
            }

            // Validate configuration
            $validationResult = $this->validator->validateSafe($data);
            if (!$validationResult->isValid()) {
                $errors = implode("\n", $validationResult->getErrors());
                throw new ConfigurationLoadException("Invalid configuration:\n$errors", $path);
            }

            return $data;
        } catch (ConfigurationFileNotFoundException|ConfigurationFileNotReadableException|ConfigurationLoadException $e) {
            // Re-throw configuration-specific exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            throw new ConfigurationLoadException('Failed to load configuration: ' . $e->getMessage(), $path, $e);
        }
    }

    private function interpolateEnvironmentVariables(string $content): string
    {
        return preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*):?([^}]*)\}/',
            function (array $matches): string {
                $envVar = $matches[1];
                $default = $matches[2];

                // Handle syntax: ${VAR:-default}
                if (str_starts_with($default, '-')) {
                    $default = substr($default, 1);
                }

                // Use security service for safe environment variable access
                try {
                    return $this->securityService->getEnvironmentVariable($envVar, $default);
                } catch (\RuntimeException $e) {
                    if ($default !== '') {
                        return $default;
                    }
                    throw $e;
                }
            },
            $content,
        );
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
                // If both arrays are indexed (not associative), replace rather than merge
                if ($this->isIndexedArray($value) && $this->isIndexedArray($merged[$key])) {
                    $merged[$key] = $value;
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
