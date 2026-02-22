<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Service\FilesystemService;

/**
 * Validates Fractor configuration files.
 */
final class FractorConfigurationValidator implements ToolConfigurationValidatorInterface
{
    use ToolValidationTrait;

    public const string TOOL_NAME = 'fractor';
    public const array SUPPORTED_EXTENSIONS = ['php'];

    private const array FRACTOR_PATTERNS = [
        'FractorConfig',
        'fractor',
        'Fractor',
        'paths(',
        'rules(',
        'rule(',
        'sets(',
        'TypoScript',
        'typoscript',
    ];


    public function validateConfigurationFile(string $path): bool
    {
        $this->lastError = null;

        // Perform basic file checks using trait
        if (!$this->performBasicFileChecks($path)) {
            return false;
        }

        // Validate PHP syntax using consistent method
        if (!$this->validatePhpSyntaxWithTempFile($path, 'fractor')) {
            return false;
        }

        // Validate Fractor-specific structure
        return $this->validateFractorStructure($path);
    }

    /**
     * Validate Fractor-specific configuration structure.
     */
    private function validateFractorStructure(string $path): bool
    {
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            // Check for Fractor-specific content using shared method
            if (!$this->containsAnyPattern($content, self::FRACTOR_PATTERNS)) {
                $this->lastError = 'File does not appear to be a valid Fractor configuration';
                return false;
            }

            // Try to include the file to check if it returns something valid
            try {
                $config = include $path;
                if (!\is_callable($config) && !\is_array($config)) {
                    $this->lastError = 'Fractor configuration must return a callable or configuration array';
                    return false;
                }
            } catch (\Throwable $e) {
                $this->lastError = "Error including configuration file: {$e->getMessage()}";
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Structure validation failed: {$e->getMessage()}";
            return false;
        }
    }
}
