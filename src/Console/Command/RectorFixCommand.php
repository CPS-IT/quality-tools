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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configPath = $this->resolveConfigPath('rector.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getVendorBinPath() . '/rector',
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
