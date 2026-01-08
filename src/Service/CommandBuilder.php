<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

/**
 * Service responsible for building and modifying command arrays for process execution
 *
 * Handles memory limit injection, PHP command modification, and command preparation
 * for different execution scenarios.
 */
final class CommandBuilder
{
    /**
     * Prepare command with memory limit if needed
     */
    public function prepareCommandWithMemoryLimit(array $command, ?string $memoryLimit = null): array
    {
        if ($memoryLimit === null) {
            return $command;
        }

        return $this->shouldInjectMemoryLimit($command)
            ? $this->injectMemoryLimit($command, $memoryLimit)
            : $command;
    }

    /**
     * Check if a memory limit should be injected into the command
     */
    private function shouldInjectMemoryLimit(array $command): bool
    {
        if (empty($command)) {
            return false;
        }

        $executable = basename($command[0]);

        return str_contains($executable, 'php')
            || str_ends_with($command[0], '.php')
            || str_ends_with($command[0], '.phar');
    }

    /**
     * Inject memory limit into the PHP command
     */
    private function injectMemoryLimit(array $command, string $memoryLimit): array
    {
        return array_merge(['php', '-d', 'memory_limit=' . $memoryLimit], $command);
    }
}
