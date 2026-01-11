<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Traits;

use Cpsit\QualityTools\Exception\ConfigurationFileNotFoundException;
use Cpsit\QualityTools\Exception\ConfigurationFileNotReadableException;
use Cpsit\QualityTools\Exception\ConfigurationLoadException;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait providing YAML file loading functionality.
 *
 * This trait provides methods to load and parse YAML configuration files
 * with proper validation and error handling.
 */
trait YamlFileLoaderTrait
{
    /**
     * Load and parse YAML configuration file with validation.
     *
     * @param string $path The path to the YAML file
     *
     * @throws ConfigurationLoadException When the file cannot be loaded or is invalid
     *
     * @return array The parsed and validated configuration data
     */
    protected function loadYamlFile(string $path): array
    {
        try {
            $content = $this->readConfigurationFile($path);

            // Apply environment variable interpolation
            $content = $this->interpolateEnvironmentVariables($content);

            $data = Yaml::parse($content);
            if (!\is_array($data)) {
                throw new ConfigurationLoadException('Configuration file must contain valid YAML data', $path);
            }

            // Validate configuration if validator is available
            if (isset($this->validator)) {
                $validationResult = $this->validator->validateSafe($data);
                if (!$validationResult->isValid()) {
                    $errors = implode("\n", $validationResult->getErrors());
                    throw new ConfigurationLoadException("Invalid configuration:\n$errors", $path);
                }
            }

            return $data;
        } catch (ConfigurationFileNotFoundException|ConfigurationFileNotReadableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ConfigurationLoadException('Failed to load YAML configuration: ' . $e->getMessage(), $path, $e);
        }
    }
}
