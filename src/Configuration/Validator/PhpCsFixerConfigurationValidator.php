<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Service\FilesystemService;

/**
 * Validates PHP-CS-Fixer configuration files.
 */
final class PhpCsFixerConfigurationValidator implements ToolConfigurationValidatorInterface
{
    use ToolValidationTrait;

    public const string TOOL_NAME = 'php-cs-fixer';
    public const array SUPPORTED_EXTENSIONS = ['php'];

    private const array PHP_CS_FIXER_PATTERNS = [
        'PhpCsFixer\\Config',
        'php-cs-fixer',
        'PhpCsFixer',
        'setFinder(',
        'setRules(',
        'setRiskyAllowed(',
        'Finder::create()',
        'Config::create()',
        'new Config(',
    ];


    public function validateConfigurationFile(string $path): bool
    {
        $this->lastError = null;

        // Perform basic file checks using trait
        if (!$this->performBasicFileChecks($path)) {
            return false;
        }

        // Validate PHP syntax using consistent method
        if (!$this->validatePhpSyntaxWithTempFile($path, 'php_cs_fixer')) {
            return false;
        }

        // Validate PHP-CS-Fixer specific structure
        return $this->validatePhpCsFixerStructure($path);
    }

    /**
     * Validate PHP-CS-Fixer specific configuration structure.
     */
    private function validatePhpCsFixerStructure(string $path): bool
    {
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            // Check for PHP-CS-Fixer specific content using shared method
            if (!$this->containsAnyPattern($content, self::PHP_CS_FIXER_PATTERNS)) {
                $this->lastError = 'File does not appear to be a valid PHP-CS-Fixer configuration';
                return false;
            }

            // Try to include the file to check if it returns a Config instance
            try {
                $config = include $path;
                if (!$config instanceof \PhpCsFixer\Config && !\is_array($config) && !\is_callable($config)) {
                    $this->lastError = 'PHP-CS-Fixer configuration must return a Config instance, array, or callable';
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
