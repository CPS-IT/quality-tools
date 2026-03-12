<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Exception;

/**
 * Exception thrown when configuration validation fails.
 */
final class ConfigurationValidationException extends \RuntimeException
{
    /**
     * @param array<array<string, mixed>> $validationErrors Raw validation errors from JSON schema validator
     */
    public function __construct(
        string $message = '',
        private readonly array $validationErrors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the raw validation errors from the JSON schema validator.
     *
     * @return array<array<string, mixed>>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get formatted error messages for display to users.
     *
     * @return array<string>
     */
    public function getFormattedErrors(): array
    {
        $formatted = [];

        foreach ($this->validationErrors as $error) {
            $property = $error['property'] ?? 'root';
            $message = $error['message'] ?? 'Unknown validation error';
            $constraint = $error['constraint'] ?? '';

            $formatted[] = match ($constraint) {
                'required' => \sprintf('Missing required property: %s', $property),
                'additionalProperties' => \sprintf('Unknown property: %s (%s)', $property, $message),
                'enum' => \sprintf('Invalid value for %s: %s', $property, $message),
                'pattern' => \sprintf('Invalid format for %s: %s', $property, $message),
                'type' => \sprintf('Wrong type for %s: %s', $property, $message),
                'minimum', 'maximum' => \sprintf('Value out of range for %s: %s', $property, $message),
                'minLength', 'maxLength' => \sprintf('Invalid length for %s: %s', $property, $message),
                'minItems', 'maxItems' => \sprintf('Invalid array size for %s: %s', $property, $message),
                default => \sprintf('%s: %s', $property, $message),
            };
        }

        return $formatted;
    }
}
