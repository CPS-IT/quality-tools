<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Service responsible for executing processes with proper output handling.
 *
 * Handles process creation, execution, output forwarding, and exit code management
 * with support for verbose mode and error output handling.
 */
final class ProcessExecutor
{
    /**
     * Execute a process with proper output handling.
     */
    public function executeProcess(
        array $command,
        string $workingDirectory,
        array $environment,
        OutputInterface $output,
    ): int {
        $process = new Process($command, $workingDirectory, $environment);

        $this->handleVerboseOutput($output, $process);

        $process->run(function (string $type, string $buffer) use ($output): void {
            $this->forwardProcessOutput($type, $buffer, $output);
        });

        return $process->getExitCode() ?? 1;
    }

    /**
     * Handle verbose output before process execution.
     */
    private function handleVerboseOutput(OutputInterface $output, Process $process): void
    {
        if ($output->isVerbose()) {
            $output->writeln(\sprintf('<info>Executing: %s</info>', $process->getCommandLine()));
        }
    }

    /**
     * Forward process output to the appropriate output stream.
     */
    private function forwardProcessOutput(string $type, string $buffer, OutputInterface $output): void
    {
        if ($type === Process::ERR) {
            $this->forwardErrorOutput($buffer, $output);
        } else {
            $output->write($buffer);
        }
    }

    /**
     * Forward error output to the appropriate error stream.
     */
    private function forwardErrorOutput(string $buffer, OutputInterface $output): void
    {
        // Check if output supports getErrorOutput() method (ConsoleOutputInterface)
        if (method_exists($output, 'getErrorOutput')) {
            $output->getErrorOutput()->write($buffer);
        } else {
            // For outputs that don't support error output (like StreamOutput), write to the main output
            $output->write($buffer);
        }
    }
}
