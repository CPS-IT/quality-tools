<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Messaging\OutputCollectorInterface;
use Symfony\Component\Process\Process;

/**
 * Service responsible for executing processes with proper output handling.
 *
 * Handles process creation, execution, output forwarding, and exit code management
 * with support for verbose mode and error output handling.
 */
final readonly class ProcessExecutor
{
    /**
     * Analysis tools legitimately run for minutes on larger projects, so
     * Symfony's default of 60 seconds would abort them. The limit only
     * catches a hanging tool, well before a typical CI job timeout.
     */
    public const int PROCESS_TIMEOUT_SECONDS = 1800;

    /** @var \Closure(list<string>, string, array<string, string>): Process */
    private \Closure $processFactory;

    /**
     * @param (\Closure(list<string>, string, array<string, string>): Process)|null $processFactory
     */
    public function __construct(
        ?\Closure $processFactory = null,
    ) {
        $this->processFactory = $processFactory
            ?? static fn (array $command, string $cwd, array $env): Process => new Process($command, $cwd, $env);
    }

    /**
     * Execute a process forwarding output to an OutputCollector.
     *
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public function executeWithCollector(
        array $command,
        string $workingDirectory,
        array $environment,
        OutputCollectorInterface $collector,
    ): int {
        $process = ($this->processFactory)($command, $workingDirectory, $environment);
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);

        $process->run(function (string $type, string $buffer) use ($collector): void {
            if ($type === Process::ERR) {
                $collector->writeError($buffer);
            } else {
                $collector->write($buffer);
            }
        });

        return $process->getExitCode() ?? 1;
    }
}
