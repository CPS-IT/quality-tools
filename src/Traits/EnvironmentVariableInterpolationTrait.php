<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Traits;

/**
 * Trait providing environment variable interpolation functionality.
 *
 * This trait provides a method to interpolate environment variables in configuration content
 * using the ${VAR} or ${VAR:-default} syntax.
 */
trait EnvironmentVariableInterpolationTrait
{
    /**
     * Interpolate environment variables in configuration content.
     *
     * Supports the following syntax:
     * - ${VAR} - Replace with environment variable VAR
     * - ${VAR:-default} - Replace with environment variable VAR, or use 'default' if not set
     *
     * @param string $content The content to interpolate
     *
     * @throws \RuntimeException When the required environment variable is not set and no default provided
     *
     * @return string The content with environment variables interpolated
     */
    protected function interpolateEnvironmentVariables(string $content): string
    {
        return preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*):?([^}]*)\}/',
            function (array $matches): string {
                $envVar = $matches[1];
                $default = $matches[2];

                // Handle syntax: ${VAR:-default}
                if (str_starts_with($default, '-')) {
                    $default = substr($default, 1);
                }

                try {
                    return $this->securityService->getEnvironmentVariable($envVar, $default);
                } catch (\RuntimeException $e) {
                    if ($default !== '') {
                        return $default;
                    }
                    throw $e;
                }
            },
            $content,
        );
    }
}
