<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RectorLintCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:rector')
            ->setDescription('Run Rector in dry-run mode to analyze code without making changes')
            ->setHelp(
                'This command runs Rector in dry-run mode to show what changes would be made ' .
                'without actually modifying your code files. Use --config to specify a custom ' .
                'configuration file or --path to target specific directories.'
            );
    }

    protected function getTargetPath(InputInterface $input): string
    {
        if ($this->cachedTargetPath === null) {
            // If user specified a custom path, use it
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
                }
                $this->cachedTargetPath = realpath($customPath);
            } else {
                // For Rector, default to packages directory if it exists (matches rector.php config)
                $packagesPath = $this->getProjectRoot() . '/packages';
                if (is_dir($packagesPath)) {
                    $this->cachedTargetPath = $packagesPath;
                } else {
                    // Fall back to project root
                    $this->cachedTargetPath = $this->getProjectRoot();
                }
            }
        }

        return $this->cachedTargetPath;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getOption('show-optimization')) {
                $this->showOptimizationDetails($input, $output, 'rector');
            }

            $configPath = $this->resolveConfigPath('rector.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getVendorBinPath() . '/rector',
                'process',
                '--dry-run',
                '--config=' . $configPath
            ];

            // Note: Rector does not support parallel processing via command line

            // Note: Rector shows progress by default, no specific option needed

            $command[] = $targetPath;

            // Get optimal memory limit only if optimization is enabled
            $memoryLimit = null;
            if (!$this->isOptimizationDisabled($input)) {
                $memoryLimit = $this->getOptimalMemoryLimit($input, $output, 'rector');
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<info>Using automatic memory limit: %s</info>', $memoryLimit));
                }
            }

            return $this->executeProcess($command, $input, $output, $memoryLimit);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
