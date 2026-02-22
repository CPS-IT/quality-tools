<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Configuration\Validator\ToolConfigurationValidatorInterface;

/**
 * Service for validating tool-specific configuration files.
 */
final class ToolConfigurationValidationService
{
    /**
     * @var array<string, ToolConfigurationValidatorInterface>
     */
    private array $validators = [];

    /**
     * @param iterable<ToolConfigurationValidatorInterface> $validators Tagged validators
     */
    public function __construct(iterable $validators = [])
    {
        foreach ($validators as $validator) {
            $this->registerValidator($validator);
        }
    }

    /**
     * Register a validator for a specific tool.
     */
    public function registerValidator(ToolConfigurationValidatorInterface $validator): void
    {
        $this->validators[$validator->getToolName()] = $validator;
    }

    /**
     * Validate a configuration file for a specific tool.
     */
    public function validateConfigurationFile(string $tool, string $path): bool
    {
        $validator = $this->getValidator($tool);
        if ($validator === null) {
            return false;
        }

        return $validator->validateConfigurationFile($path);
    }

    /**
     * Get validation error for a specific tool.
     */
    public function getLastError(string $tool): ?string
    {
        $validator = $this->getValidator($tool);
        if ($validator === null) {
            return "No validator registered for tool: {$tool}";
        }

        return $validator->getLastError();
    }

    /**
     * Check if a tool has a registered validator.
     */
    public function hasValidator(string $tool): bool
    {
        return isset($this->validators[$tool]);
    }

    /**
     * Get all registered tool names.
     *
     * @return string[]
     */
    public function getRegisteredTools(): array
    {
        return array_keys($this->validators);
    }

    /**
     * Get supported extensions for a specific tool.
     *
     * @return string[]
     */
    public function getSupportedExtensions(string $tool): array
    {
        $validator = $this->getValidator($tool);
        if ($validator === null) {
            return [];
        }

        return $validator->getSupportedExtensions();
    }

    /**
     * Validate multiple configuration files and return results.
     *
     * @param array<string, string> $toolConfigPaths Array of tool => path mappings
     *
     * @return array<string, array{valid: bool, error: string|null}>
     */
    public function validateMultipleConfigurations(array $toolConfigPaths): array
    {
        $results = [];

        foreach ($toolConfigPaths as $tool => $path) {
            $isValid = $this->validateConfigurationFile($tool, $path);
            $results[$tool] = [
                'valid' => $isValid,
                'error' => $isValid ? null : $this->getLastError($tool),
            ];
        }

        return $results;
    }

    /**
     * Get validator for a specific tool.
     */
    private function getValidator(string $tool): ?ToolConfigurationValidatorInterface
    {
        return $this->validators[$tool] ?? null;
    }
}
