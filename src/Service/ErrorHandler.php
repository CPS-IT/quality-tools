<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Service;

use Cpsit\QualityTools\Exception\QualityToolsException;
use Cpsit\QualityTools\Exception\TransientException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Service for handling errors with structured responses and retry mechanisms.
 */
class ErrorHandler
{
    private const int MAX_RETRY_ATTEMPTS = 3;
    private const int BASE_RETRY_DELAY = 1; // seconds

    /**
     * Handle an exception with structured error reporting.
     */
    public function handleException(
        \Throwable $exception,
        OutputInterface $output,
        bool $verbose = false,
    ): int {
        if ($exception instanceof QualityToolsException) {
            return $this->handleStructuredException($exception, $output, $verbose);
        }

        return $this->handleGenericException($exception, $output, $verbose);
    }

    /**
     * Handle QualityToolsException with structured information.
     */
    private function handleStructuredException(
        QualityToolsException $exception,
        OutputInterface $output,
        bool $verbose,
    ): int {
        $errorInfo = $exception->getErrorInfo();

        // Output error header
        $output->writeln(\sprintf(
            '<error>%s Error (%d): %s</error>',
            ucfirst((string) $errorInfo['type']),
            $errorInfo['code'],
            $errorInfo['message'],
        ));

        // Show context in verbose mode
        if ($verbose && !empty($errorInfo['context'])) {
            $output->writeln('<comment>Context:</comment>');
            foreach ($errorInfo['context'] as $key => $value) {
                $output->writeln(\sprintf('  %s: %s', $key, $this->formatContextValue($value)));
            }
        }

        // Show troubleshooting steps
        $troubleshootingSteps = $exception->getTroubleshootingSteps();
        if (!empty($troubleshootingSteps)) {
            $output->writeln('<comment>Troubleshooting:</comment>');
            foreach ($troubleshootingSteps as $i => $step) {
                $output->writeln(\sprintf('  %d. %s', $i + 1, $step));
            }
        }

        // Show additional help for common error types
        $this->showAdditionalHelp($exception, $output);

        return $exception->getSuggestedExitCode();
    }

    /**
     * Handle generic exceptions.
     */
    private function handleGenericException(
        \Throwable $exception,
        OutputInterface $output,
        bool $verbose,
    ): int {
        $output->writeln(\sprintf('<error>Unexpected Error: %s</error>', $exception->getMessage()));

        if ($verbose) {
            $output->writeln('<comment>Technical Details:</comment>');
            $output->writeln(\sprintf('  Exception: %s', $exception::class));
            $output->writeln(\sprintf('  File: %s:%d', $exception->getFile(), $exception->getLine()));

            if ($exception->getPrevious() instanceof \Throwable) {
                $output->writeln(\sprintf('  Previous: %s', $exception->getPrevious()->getMessage()));
            }
        }

        $output->writeln('<comment>Troubleshooting:</comment>');
        $output->writeln('  1. Run the command with --verbose (-v) for more details');
        $output->writeln('  2. Check that all required tools are properly installed');
        $output->writeln('  3. Verify your configuration files are valid');
        $output->writeln('  4. Report this issue if it persists');

        return 1;
    }

    /**
     * Execute an operation with automatic retry for transient failures.
     */
    public function executeWithRetry(
        callable $operation,
        OutputInterface $output,
        int $maxAttempts = self::MAX_RETRY_ATTEMPTS,
    ): mixed {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                return $operation();
            } catch (TransientException $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $delay = $this->calculateRetryDelay($attempt, $e->getRetryAfter());

                if ($output->isVerbose()) {
                    $output->writeln(\sprintf(
                        '<comment>Transient error (attempt %d/%d): %s. Retrying in %d seconds...</comment>',
                        $attempt,
                        $maxAttempts,
                        $e->getMessage(),
                        $delay,
                    ));
                }

                sleep($delay);
                ++$attempt;
            } catch (\Throwable $e) {
                // Non-transient errors should not be retried
                throw $e;
            }
        }

        // If we reach here, all retry attempts failed
        throw $lastException;
    }

    /**
     * Calculate retry delay with exponential backoff.
     */
    private function calculateRetryDelay(int $attempt, int $baseDelay = self::BASE_RETRY_DELAY): int
    {
        return min($baseDelay * (2 ** ($attempt - 1)), 30); // Cap at 30 seconds
    }

    /**
     * Show additional help based on error type.
     */
    private function showAdditionalHelp(QualityToolsException $exception, OutputInterface $output): void
    {
        $errorType = $exception->getErrorType();

        switch ($errorType) {
            case 'configuration':
                $output->writeln('<comment>Additional Help:</comment>');
                $output->writeln('  - Use --config option to specify a custom configuration file');
                $output->writeln('  - Check the default configuration in vendor/cpsit/quality-tools/config/');
                $output->writeln('  - Verify all required configuration sections are present');
                break;

            case 'process':
                $output->writeln('<comment>Additional Help:</comment>');
                $output->writeln('  - Check if the tool binary is installed and accessible');
                $output->writeln('  - Try running with --no-optimization to use default settings');
                $output->writeln('  - Verify sufficient system resources (memory, disk space)');
                break;

            case 'filesystem':
                $output->writeln('<comment>Additional Help:</comment>');
                $output->writeln('  - Check file and directory permissions');
                $output->writeln('  - Verify the target paths exist and are accessible');
                $output->writeln('  - Ensure sufficient disk space is available');
                break;

            case 'transient':
                $output->writeln('<comment>Additional Help:</comment>');
                $output->writeln('  - This error may resolve automatically on retry');
                $output->writeln('  - Check network connectivity if applicable');
                $output->writeln('  - Monitor system resources (memory, CPU usage)');
                break;
        }
    }

    /**
     * Format context value for display.
     */
    private function formatContextValue(mixed $value): string
    {
        if (\is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        if (\is_object($value)) {
            return $value::class;
        }

        return (string) $value;
    }

    /**
     * Create error response array for API/structured responses.
     */
    public function createErrorResponse(\Throwable $exception): array
    {
        if ($exception instanceof QualityToolsException) {
            return $exception->getErrorInfo();
        }

        return [
            'type' => 'unexpected',
            'code' => $exception->getCode() ?: 1,
            'message' => $exception->getMessage(),
            'troubleshooting' => [
                'Run the command with --verbose for more details',
                'Check that all required tools are properly installed',
                'Verify your configuration files are valid',
            ],
            'context' => [
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
