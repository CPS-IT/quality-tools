<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerLintCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:composer')
            ->setDescription('Run composer-normalize in dry-run mode to check composer.json formatting')
            ->setHelp(
                'This command runs composer-normalize in dry-run mode to check if composer.json ' .
                'files are properly formatted without making changes. Use --path to target ' .
                'specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $targetPath = $this->getTargetPath($input);
            $composerJsonPath = $targetPath . '/composer.json';

            // Check if composer.json exists
            if (!file_exists($composerJsonPath)) {
                throw new \InvalidArgumentException(
                    sprintf('composer.json file not found at: %s', $composerJsonPath)
                );
            }

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
                '--dry-run',
                '--diff',
                $composerJsonPath
            ];

            $output->writeln(sprintf('<comment>Checking composer.json normalization: %s</comment>', $composerJsonPath));

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
