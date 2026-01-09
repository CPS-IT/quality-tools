<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RectorFixCommand extends AbstractToolCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:rector')
            ->setDescription('Run Rector to automatically fix and upgrade code')
            ->setHelp(
                'This command runs Rector to automatically apply code fixes and upgrades. ' .
                'This will modify your code files! Use --config to specify a custom ' .
                'configuration file or --path to target specific directories.',
            );
    }

    protected function getToolName(): string
    {
        return 'rector';
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'rector.php';
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        $command = [
            $this->getVendorBinPath() . '/rector',
            'process',
            '--config=' . $configPath,
        ];

        // Add target paths to command if available
        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        return $command;
    }
}
