<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerFixCommand extends BaseCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:composer')
            ->setDescription('Run composer-normalize to format composer.json files')
            ->setHelp(
                'This command runs composer-normalize to format composer.json files according ' .
                'to normalized standards. This will modify your composer.json file! Use --path ' .
                'to target specific directories.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(\sprintf('Target path does not exist or is not a directory: %s', $customPath));
                }
                $targetPaths = [realpath($customPath)];
            } else {
                // Use resolved paths from configuration - check all paths for composer.json files
                $targetPaths = $this->getResolvedPathsForTool($input, 'composer');
            }

            $totalExitCode = 0;
            $foundFiles = 0;

            foreach ($targetPaths as $targetPath) {
                $composerJsonPath = $targetPath . '/composer.json';

                // Check if composer.json exists in this path
                if (!file_exists($composerJsonPath)) {
                    if ($output->isVerbose()) {
                        $output->writeln(\sprintf('<comment>No composer.json found at: %s</comment>', $targetPath));
                    }
                    continue;
                }

                ++$foundFiles;

                // Use composer normalize plugin command
                // Check if composer exists in vendor/bin (for tests), otherwise use system composer
                $composerExecutable = 'composer';
                $vendorComposer = $this->getVendorBinPath() . '/composer';
                if (file_exists($vendorComposer)) {
                    $composerExecutable = $vendorComposer;
                }

                $command = [
                    $composerExecutable,
                    'normalize',
                    $composerJsonPath,
                ];

                $output->writeln(\sprintf('<comment>Normalizing composer.json: %s</comment>', $composerJsonPath));

                $exitCode = $this->executeProcess($command, $input, $output);
                if ($exitCode !== 0) {
                    $totalExitCode = $exitCode;
                }
            }

            if ($foundFiles === 0) {
                $output->writeln('<comment>No composer.json files found in any of the configured paths</comment>');

                return 1;
            }

            return $totalExitCode;
        } catch (\Exception $e) {
            $output->writeln(\sprintf('<error>Error: %s</error>', $e->getMessage()));

            return 1;
        }
    }
}
