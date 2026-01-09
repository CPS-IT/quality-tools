<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RectorLintCommand extends AbstractToolCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:rector')
            ->setDescription('Run Rector in dry-run mode to analyze code without making changes')
            ->setHelp(
                'This command runs Rector in dry-run mode to show what changes would be made ' .
                'without actually modifying your code files. Use --config to specify a custom ' .
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
            '--dry-run',
            '--config=' . $configPath,
        ];

        // Add target paths to command if available
        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        return $command;
    }
}
