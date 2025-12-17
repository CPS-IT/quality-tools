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

            $command = [
                $this->getVendorBinPath() . '/composer-normalize',
                '--dry-run',
                '--diff',
                $targetPath . '/composer.json'
            ];

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
