<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCsFixerLintCommand extends BaseCommand
{
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
                // For PHP CS Fixer, default to packages directory if it exists (typical TYPO3 setup)
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

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:php-cs-fixer')
            ->setDescription('Run PHP CS Fixer in dry-run mode to check code style issues')
            ->setHelp(
                'This command runs PHP CS Fixer in dry-run mode to show what code style ' .
                'issues would be fixed without actually modifying your code files. Use ' .
                '--config to specify a custom configuration file or --path to target ' .
                'specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getOption('show-optimization')) {
                $this->showOptimizationDetails($input, $output, 'php-cs-fixer');
            }

            $configPath = $this->resolveConfigPath('php-cs-fixer.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getVendorBinPath() . '/php-cs-fixer',
                'fix',
                '--dry-run',
                '--diff',
                '--config=' . $configPath
            ];

            // Enable parallel processing if beneficial
            if ($this->shouldEnableParallelProcessing($input, $output)) {
                $command[] = '--using-cache=yes';
            }

            $command[] = $targetPath;

            // Get optimal memory limit only if optimization is enabled
            $memoryLimit = null;
            if (!$this->isOptimizationDisabled($input)) {
                $memoryLimit = $this->getOptimalMemoryLimit($input, $output, 'php-cs-fixer');
            }

            return $this->executeProcess($command, $input, $output, $memoryLimit);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
