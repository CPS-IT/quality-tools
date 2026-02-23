<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Service\FilesystemService;

/**
 * Validates Rector configuration files.
 */
final class RectorConfigurationValidator implements ToolConfigurationValidatorInterface
{
    use ToolValidationTrait;

    public const string TOOL_NAME = 'rector';
    public const array SUPPORTED_EXTENSIONS = ['php'];

    private const array RECTOR_PATTERNS = [
        'RectorConfig',
        'rector',
        'paths(',
        'rules(',
        'rule(',
        'sets(',
    ];

    public function validateConfigurationFile(string $path): bool
    {
        $this->lastError = null;

        // Perform basic file checks using trait
        if (!$this->performBasicFileChecks($path)) {
            return false;
        }

        // Validate PHP syntax using consistent method
        if (!$this->validatePhpSyntaxWithTempFile($path, 'rector')) {
            return false;
        }

        // Validate Rector-specific structure
        return $this->validateRectorStructure($path);
    }

    /**
     * Validate Rector-specific configuration structure.
     */
    private function validateRectorStructure(string $path): bool
    {
        try {
            // Check if file returns a callable (standard Rector config pattern)
            $config = include $path;
            if (!\is_callable($config)) {
                $this->lastError = 'Rector configuration must return a callable';

                return false;
            }

            // Check for Rector-specific content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            if (!$this->containsAnyPattern($content, self::RECTOR_PATTERNS)) {
                $this->lastError = 'File does not appear to be a valid Rector configuration';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Structure validation failed: {$e->getMessage()}";

            return false;
        }
    }
}
