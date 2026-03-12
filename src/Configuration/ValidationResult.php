<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration;

final readonly class ValidationResult
{
    public function __construct(private bool $valid, private array $errors = [])
    {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
