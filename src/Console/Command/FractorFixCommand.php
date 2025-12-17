<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FractorFixCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:fractor')
            ->setDescription('Run Fractor to apply TypoScript and code changes')
            ->setHelp(
                'This command runs Fractor to apply TypoScript and code changes to your files. ' .
                'This will modify your files! Use --config to specify a custom configuration ' .
                'file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configPath = $this->resolveConfigPath('fractor.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getVendorBinPath() . '/fractor',
                'process',
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
