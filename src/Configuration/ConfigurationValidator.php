<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

use Cpsit\QualityTools\Exception\ConfigurationValidationException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validates YAML configuration against JSON schema.
 */
final class ConfigurationValidator
{
    private const string SCHEMA_FILE = __DIR__ . '/../../config/schema/quality-tools.json';

    private ?array $schema = null;

    public function __construct(private readonly Validator $validator = new Validator())
    {
    }

    /**
     * Validates configuration data against the JSON schema.
     *
     * @param array<string, mixed> $data The configuration data to validate
     *
     * @throws ConfigurationValidationException If validation fails
     * @throws \JsonException
     */
    public function validate(array $data): void
    {
        $schema = $this->getSchema();
        $dataObject = json_decode(
            json_encode($data, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->validator->validate($dataObject, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

        if (!$this->validator->isValid()) {
            throw new ConfigurationValidationException('Configuration validation failed', $this->validator->getErrors());
        }
    }

    /**
     * Validates configuration data and returns a validation result without throwing.
     *
     * @param array<string, mixed> $data The configuration data to validate
     */
    public function validateSafe(array $data): ValidationResult
    {
        try {
            $this->validate($data);

            return new ValidationResult(true, []);
        } catch (ConfigurationValidationException $e) {
            return new ValidationResult(false, $e->getFormattedErrors());
        }
    }

    /**
     * Validate that tool config_file paths reference existing files.
     *
     * @param array<string, mixed> $config      The full configuration array
     * @param string               $projectRoot The project root directory
     *
     * @return list<string> Warning messages for invalid paths
     */
    public function validateToolConfigFilePaths(array $config, string $projectRoot): array
    {
        $warnings = [];
        $tools = $config['quality-tools']['tools'] ?? [];

        foreach ($tools as $tool => $toolConfig) {
            if (!isset($toolConfig['config_file'])) {
                continue;
            }

            $configFile = $toolConfig['config_file'];

            // Skip absolute paths -- these come from auto-discovery and are always valid
            if (str_starts_with($configFile, '/')) {
                continue;
            }

            $resolvedPath = $projectRoot . '/' . $configFile;

            if (!file_exists($resolvedPath)) {
                $warnings[] = \sprintf(
                    'Tool "%s": config_file "%s" does not exist',
                    $tool,
                    $configFile,
                );
            }
        }

        return $warnings;
    }

    /**
     * Get the JSON schema for validation.
     *
     * @throws ConfigurationValidationException If schema cannot be loaded
     *
     * @return object The decoded JSON schema
     */
    private function getSchema(): object
    {
        if ($this->schema === null) {
            if (!file_exists(self::SCHEMA_FILE)) {
                throw new ConfigurationValidationException('Configuration schema file not found: ' . self::SCHEMA_FILE);
            }

            $schemaContent = file_get_contents(self::SCHEMA_FILE);
            if ($schemaContent === false) {
                throw new ConfigurationValidationException('Failed to read configuration schema file: ' . self::SCHEMA_FILE);
            }

            $this->schema = json_decode($schemaContent, true);
            if ($this->schema === null) {
                throw new ConfigurationValidationException('Invalid JSON in configuration schema file: ' . self::SCHEMA_FILE);
            }
        }

        return json_decode(json_encode($this->schema), false);
    }
}
