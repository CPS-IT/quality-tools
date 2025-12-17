<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FractorLintCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:fractor')
            ->setDescription('Run Fractor in dry-run mode to analyze TypoScript and code without making changes')
            ->setHelp(
                'This command runs Fractor in dry-run mode to show what TypoScript and code ' .
                'changes would be made without actually modifying your files. Use --config ' .
                'to specify a custom configuration file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configPath = $this->resolveConfigPath('fractor.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getProjectRoot() . '/vendor/bin/fractor',
                'process',
                '--dry-run',
                '--config=' . $configPath,
                $targetPath
            ];

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
