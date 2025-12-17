<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerFixCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:composer')
            ->setDescription('Run composer-normalize to format composer.json files')
            ->setHelp(
                'This command runs composer-normalize to format composer.json files according ' .
                'to normalized standards. This will modify your composer.json file! Use --path ' .
                'to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getProjectRoot() . '/vendor/bin/composer-normalize',
                $targetPath . '/composer.json'
            ];

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
