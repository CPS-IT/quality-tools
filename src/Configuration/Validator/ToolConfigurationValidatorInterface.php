<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

/**
 * Interface for validating tool-specific configuration files.
 */
interface ToolConfigurationValidatorInterface
{
    /**
     * Validate that a configuration file is valid for this tool.
     *
     * @param string $path Path to the configuration file
     *
     * @return bool True if the configuration file is valid, false otherwise
     */
    public function validateConfigurationFile(string $path): bool;

    /**
     * Get the tool name this validator handles.
     *
     * @return string Tool name (e.g., 'rector', 'phpstan')
     */
    public function getToolName(): string;

    /**
     * Get supported file extensions for this tool's configuration.
     *
     * @return string[] Array of supported extensions (without dots)
     */
    public function getSupportedExtensions(): array;

    /**
     * Get validation error message if the last validation failed.
     *
     * @return string|null Error message or null if no error
     */
    public function getLastError(): ?string;
}
