<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Service responsible for preparing environment variables for process execution
 *
 * Handles memory limit configuration, dynamic path injection, and environment variable setup
 * for different quality tools.
 */
final class ProcessEnvironmentPreparer
{
    /**
     * Prepare environment variables for process execution
     * @throws \JsonException
     */
    public function prepareEnvironment(
        InputInterface $input,
        ?string $memoryLimit = null,
        ?string $tool = null,
        ?array $resolvedPaths = null
    ): array {
        $env = $_SERVER;

        if ($memoryLimit !== null) {
            $env['PHP_MEMORY_LIMIT'] = $memoryLimit;
        }

        // Set dynamic paths environment variable for configuration-based tools like Fractor
        if ($this->shouldSetDynamicPaths($input, $tool, $resolvedPaths)) {
            $env['QT_DYNAMIC_PATHS'] = json_encode($resolvedPaths, JSON_THROW_ON_ERROR);
        }

        return $env;
    }

    /**
     * Check if dynamic paths should be set for the tool
     */
    private function shouldSetDynamicPaths(InputInterface $input, ?string $tool, ?array $resolvedPaths): bool
    {
        return $tool === 'fractor'
            && !$input->hasParameterOption('--path')
            && $resolvedPaths !== null;
    }
}
