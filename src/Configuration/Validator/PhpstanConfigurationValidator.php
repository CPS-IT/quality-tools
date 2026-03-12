<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Configuration\Validator;

use Cpsit\QualityTools\Service\FilesystemService;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates PHPStan configuration files.
 */
final class PhpstanConfigurationValidator implements ToolConfigurationValidatorInterface
{
    use ToolValidationTrait;

    public const string TOOL_NAME = 'phpstan';
    public const array SUPPORTED_EXTENSIONS = ['neon', 'neon.dist', 'php', 'yml', 'yaml'];

    public function validateConfigurationFile(string $path): bool
    {
        $this->lastError = null;

        // Perform basic file checks using trait
        if (!$this->performBasicFileChecks($path)) {
            return false;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'neon', 'dist' => $this->validateNeonFormat($path),
            'php' => $this->validatePhpFormat($path),
            default => $this->validateGenericFormat($path),
        };
    }

    /**
     * Validate NEON format PHPStan configuration.
     */
    private function validateNeonFormat(string $path): bool
    {
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            // Check for basic PHPStan NEON configuration structure
            $neonPatterns = ['parameters:', 'level:', 'paths:', 'phpstan', 'includes:', 'rules:'];
            if (!$this->containsAnyPattern($content, $neonPatterns)) {
                $this->lastError = 'File does not appear to be a valid PHPStan NEON configuration';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "NEON validation failed: {$e->getMessage()}";

            return false;
        }
    }

    /**
     * Validate PHP format PHPStan configuration.
     */
    private function validatePhpFormat(string $path): bool
    {
        // Validate PHP syntax using consistent method
        if (!$this->validatePhpSyntaxWithTempFile($path, 'phpstan')) {
            return false;
        }

        // Check for PHPStan-specific content
        try {
            // Read content using FilesystemService
            $content = $this->filesystemService->readFile($path);

            $phpstanPatterns = ['phpstan', 'PHPStan', 'level', 'paths'];
            if (!$this->containsAnyPattern($content, $phpstanPatterns)) {
                $this->lastError = 'File does not appear to be a valid PHPStan PHP configuration';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = "PHP format validation failed: {$e->getMessage()}";

            return false;
        }
    }

    /**
     * Validate generic format (YAML, etc.).
     */
    private function validateGenericFormat(string $path): bool
    {
        try {
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            if (\in_array($extension, ['yml', 'yaml'], true)) {
                // Try to parse YAML using FilesystemService
                $content = $this->filesystemService->readFile($path);

                $data = Yaml::parse($content);
                if (!\is_array($data)) {
                    $this->lastError = 'YAML file must contain configuration data';

                    return false;
                }

                return true;
            }

            // For other extensions, just check readability
            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Generic format validation failed: {$e->getMessage()}";

            return false;
        }
    }
}
