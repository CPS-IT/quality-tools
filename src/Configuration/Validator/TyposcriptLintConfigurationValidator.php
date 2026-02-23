<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Service\FilesystemService;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates TypoScript-Lint configuration files.
 */
final class TyposcriptLintConfigurationValidator implements ToolConfigurationValidatorInterface
{
    use ToolValidationTrait;

    public const string TOOL_NAME = 'typoscript-lint';
    public const array SUPPORTED_EXTENSIONS = ['yml', 'yaml'];

    private const array TYPOSCRIPT_PATTERNS = [
        'typoscript',
        'TypoScript',
        'sniffs',
        'paths',
        'fileExtensions',
    ];

    public function validateConfigurationFile(string $path): bool
    {
        $this->lastError = null;

        // Perform basic file checks using trait
        if (!$this->performBasicFileChecks($path)) {
            return false;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'yml', 'yaml' => $this->validateYamlFormat($path),
            default => $this->validateGenericFormat($path),
        };
    }

    /**
     * Validate YAML format TypoScript-Lint configuration.
     */
    private function validateYamlFormat(string $path): bool
    {
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            $data = Yaml::parse($content);
            if (!\is_array($data)) {
                $this->lastError = 'YAML file must contain configuration data';

                return false;
            }

            // Check for basic TypoScript-Lint configuration structure
            $hasTyposcriptLintContent = $this->hasTyposcriptLintStructure($data);

            if (!$hasTyposcriptLintContent) {
                $this->lastError = 'File does not appear to be a valid TypoScript-Lint configuration';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "YAML validation failed: {$e->getMessage()}";

            return false;
        }
    }

    /**
     * Validate generic format.
     */
    private function validateGenericFormat(string $path): bool
    {
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            // Basic check for TypoScript-Lint related content using shared method
            if (!$this->containsAnyPattern($content, self::TYPOSCRIPT_PATTERNS)) {
                $this->lastError = 'File does not appear to be a valid TypoScript-Lint configuration';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Generic format validation failed: {$e->getMessage()}";

            return false;
        }
    }

    /**
     * Check if data contains TypoScript-Lint configuration structure.
     */
    private function hasTyposcriptLintStructure(array $data): bool
    {
        // Check for common TypoScript-Lint configuration keys
        $typoscriptLintKeys = [
            'sniffs',
            'paths',
            'fileExtensions',
            'excludePatterns',
            'output',
            'format',
        ];

        foreach ($typoscriptLintKeys as $key) {
            if (\array_key_exists($key, $data)) {
                return true;
            }
        }

        // Check nested structure
        return isset($data['typoscript-lint']) || isset($data['typoscriptlint']);
    }
}
