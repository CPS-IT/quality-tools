<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Service\DisposableTemporaryFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpStanCommand extends BaseCommand
{
    private ?DisposableTemporaryFile $temporaryConfig = null;
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:phpstan')
            ->setDescription('Run PHPStan static analysis')
            ->setHelp(
                'This command runs PHPStan static analysis to find bugs in your code without ' .
                'running it. Use --config to specify a custom configuration file, --path to ' .
                'target specific directories, or --level to override the analysis level.'
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Override the analysis level (0-9)'
            )
            ->addOption(
                'memory-limit',
                'm',
                InputOption::VALUE_REQUIRED,
                'Memory limit for analysis (e.g., 1G, 512M)'
            );
    }

    protected function getTargetPath(InputInterface $input): string
    {
        return $this->getTargetPathForTool($input, 'phpstan');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Show optimization details by default unless disabled
            if (!$this->isOptimizationDisabled($input)) {
                $this->showOptimizationDetails($input, $output, 'phpstan');
            }

            $configPath = $this->resolveConfigPath('phpstan.neon', $input->getOption('config'));

            // Handle dynamic path resolution for PHPStan
            $customPath = $input->getOption('path');
            if ($customPath === null) {
                $resolvedPaths = $this->getResolvedPathsForTool($input, 'phpstan');
                if (count($resolvedPaths) > 1) {
                    // Create a temporary configuration file with dynamic paths
                    $configPath = $this->createTemporaryPhpStanConfig($configPath, $resolvedPaths);
                }
            }

            $command = [
                $this->getVendorBinPath() . '/phpstan',
                'analyse',
                '--configuration=' . $configPath,
            ];

            // Add a custom analysis level if specified
            $level = $input->getOption('level');
            if ($level !== null) {
                $command[] = '--level=' . $level;
            }

            // Add memory limit - use automatic optimization unless manually specified or disabled
            $memoryLimit = $input->getOption('memory-limit');
            if ($memoryLimit !== null) {
                $command[] = '--memory-limit=' . $memoryLimit;
            } elseif (!$this->isOptimizationDisabled($input)) {
                $optimalMemory = $this->getOptimalMemoryLimit($input, 'phpstan');
                $command[] = '--memory-limit=' . $optimalMemory;
            }

            // Only add a target path if the user provided a custom path via --path option
            // Otherwise, let PHPStan use paths from the configuration file (which includes resolved paths for multi-path scenarios)
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
                }
                $command[] = realpath($customPath);
            }

            $exitCode = $this->executeProcess($command, $input, $output);

            // Clean up temporary file if created
            $this->cleanupTemporaryConfig();

            return $exitCode;

        } catch (\Exception $e) {
            // Clean up temporary file on error
            $this->cleanupTemporaryConfig();
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }

    private function createTemporaryPhpStanConfig(string $baseConfigPath, array $paths): string
    {
        // Read the base configuration
        $baseConfig = file_get_contents($baseConfigPath);
        if ($baseConfig === false) {
            throw new \RuntimeException(sprintf('Could not read base config file: %s', $baseConfigPath));
        }

        // Create the dynamic paths section
        $pathsSection = "parameters:\n\tlevel: 6\n\tpaths:\n";
        foreach ($paths as $path) {
            $pathsSection .= "\t\t- " . $path . "\n";
        }

        // Create a disposable temporary file
        $this->temporaryConfig = new DisposableTemporaryFile('phpstan_', '.neon');
        $this->temporaryConfig->write($pathsSection);

        return $this->temporaryConfig->getPath();
    }

    private function cleanupTemporaryConfig(): void
    {
        if ($this->temporaryConfig !== null) {
            $this->temporaryConfig->cleanup();
            $this->temporaryConfig = null;
        }
    }
}
