<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RectorFixCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:rector')
            ->setDescription('Run Rector to automatically fix and upgrade code')
            ->setHelp(
                'This command runs Rector to automatically apply code fixes and upgrades. ' .
                'This will modify your code files! Use --config to specify a custom ' .
                'configuration file or --path to target specific directories.'
            );
    }

    protected function getTargetPath(InputInterface $input): string
    {
        return $this->getTargetPathForTool($input, 'rector');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Show optimization details by default unless disabled
            if (!$this->isOptimizationDisabled($input)) {
                $this->showOptimizationDetails($input, $output, 'rector');
            }

            $configPath = $this->resolveConfigPath('rector.php', $input->getOption('config'));

            $command = [
                $this->getVendorBinPath() . '/rector',
                'process',
                '--config=' . $configPath,
            ];

            // Handle path arguments - get option only once
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
                }
                $command[] = realpath($customPath);
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<comment>Analyzing custom path: %s</comment>', $customPath));
                }
            } else {
                // Use resolved paths from configuration - pass all paths to rector
                $resolvedPaths = $this->getResolvedPathsForTool($input, 'rector');

                if (!empty($resolvedPaths)) {
                    foreach ($resolvedPaths as $path) {
                        $command[] = $path;
                    }
                    if ($output->isVerbose()) {
                        $output->writeln(sprintf('<comment>Analyzing resolved paths: %s</comment>', implode(', ', $resolvedPaths)));
                    }
                } else {
                    if ($output->isVerbose()) {
                        $output->writeln('<comment>Using default path discovery</comment>');
                    }
                }
            }

            // Get optimal memory limit only if optimization is enabled
            $memoryLimit = null;
            if (!$this->isOptimizationDisabled($input)) {
                $memoryLimit = $this->getOptimalMemoryLimit($input, 'rector');
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
