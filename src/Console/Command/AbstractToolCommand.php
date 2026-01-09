<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractToolCommand extends BaseCommand
{
    /**
     * Template method that defines the common execution flow for all tool commands.
     * This method provides a consistent structure while allowing tool-specific customization.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Show optimization details by default unless disabled
            if (!$this->isOptimizationDisabled($input)) {
                $this->showOptimizationDetails($input, $output, $this->getToolName());
            }

            // Resolve configuration path
            $configPath = $this->resolveConfigPath($this->getDefaultConfigFileName(), $input->getOption('config'));

            // Validate tool-specific configuration if needed
            $this->validateToolConfig($input, $output, $configPath);

            // Handle path resolution
            $targetPaths = $this->resolveTargetPaths($input, $output);

            // Build the command array for the specific tool
            $command = $this->buildToolCommand($input, $output, $configPath, $targetPaths);

            // Execute pre-processing hooks if needed
            $this->executePreProcessingHooks($input, $output, $targetPaths);

            // Determine memory limit
            $memoryLimit = $this->getToolMemoryLimit($input, $output);

            // Execute the process
            $exitCode = $this->executeProcess($command, $input, $output, $memoryLimit, $this->getToolName());

            // Execute post-processing hooks if needed
            $this->executePostProcessingHooks($input, $output, $exitCode);

            return $exitCode;

        } catch (\Exception $e) {
            // Handle cleanup on exception
            $this->handleExecutionException($e, $input, $output);
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }

    /**
     * Get the name of the tool (e.g., 'rector', 'fractor', 'phpstan')
     */
    abstract protected function getToolName(): string;

    /**
     * Get the default configuration file name for the tool
     */
    abstract protected function getDefaultConfigFileName(): string;

    /**
     * Build the command array for the specific tool
     */
    abstract protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths
    ): array;

    /**
     * Validate tool-specific configuration (optional override)
     */
    protected function validateToolConfig(InputInterface $input, OutputInterface $output, string $configPath): void
    {
        // Default: no validation required
    }

    /**
     * Resolve target paths for the tool
     */
    protected function resolveTargetPaths(InputInterface $input, OutputInterface $output): array
    {
        $customPath = $input->getOption('path');
        
        if ($customPath !== null) {
            if (!is_dir($customPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Target path does not exist or is not a directory: %s', $customPath)
                );
            }
            
            $resolvedPath = realpath($customPath);
            if ($output->isVerbose()) {
                $output->writeln(sprintf('<comment>Analyzing custom path: %s</comment>', $customPath));
            }
            
            return [$resolvedPath];
        }

        // Use resolved paths from configuration
        $resolvedPaths = $this->getResolvedPathsForTool($input, $this->getToolName());

        if ($output->isVerbose()) {
            if (!empty($resolvedPaths)) {
                $output->writeln(sprintf(
                    '<comment>Analyzing resolved paths: %s</comment>',
                    implode(', ', $resolvedPaths)
                ));
            } else {
                $output->writeln('<comment>Using default path discovery</comment>');
            }
        }

        return $resolvedPaths;
    }

    /**
     * Get memory limit for the tool
     */
    protected function getToolMemoryLimit(InputInterface $input, OutputInterface $output): ?string
    {
        if ($this->isOptimizationDisabled($input)) {
            return null;
        }

        $memoryLimit = $this->getOptimalMemoryLimit($input, $this->getToolName());
        
        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>Using automatic memory limit: %s</info>', $memoryLimit));
        }

        return $memoryLimit;
    }

    /**
     * Execute pre-processing hooks (optional override)
     */
    protected function executePreProcessingHooks(InputInterface $input, OutputInterface $output, array $targetPaths): void
    {
        // Default: no pre-processing
    }

    /**
     * Execute post-processing hooks (optional override)
     */
    protected function executePostProcessingHooks(InputInterface $input, OutputInterface $output, int $exitCode): void
    {
        // Default: no post-processing
    }

    /**
     * Handle cleanup when an exception occurs (optional override)
     */
    protected function handleExecutionException(\Exception $exception, InputInterface $input, OutputInterface $output): void
    {
        // Default: no special cleanup
    }

    /**
     * Get target path for tool compatibility
     */
    protected function getTargetPath(InputInterface $input): string
    {
        return $this->getTargetPathForTool($input, $this->getToolName());
    }
}