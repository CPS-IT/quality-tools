<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCsFixerLintCommand extends AbstractToolCommand implements ToolCommandInterface
{
    public const string TOOL_NAME = 'php-cs-fixer';

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:php-cs-fixer')
            ->setDescription('Run PHP CS Fixer in dry-run mode to check code style issues')
            ->setHelp(
                'This command runs PHP CS Fixer in dry-run mode to show what code style ' .
                'issues would be fixed without actually modifying your code files. Use ' .
                '--config to specify a custom configuration file or --path to target ' .
                'specific directories.',
            );
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'php-cs-fixer.php';
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        $command = [
            $this->getVendorBinPath() . '/php-cs-fixer',
            'fix',
            '--dry-run',
            '--diff',
            '--config=' . $configPath,
        ];

        // Enable parallel processing if beneficial
        if ($this->shouldEnableParallelProcessing($input, 'php-cs-fixer')) {
            $command[] = '--using-cache=yes';
        }

        // Add target paths to command
        foreach ($targetPaths as $path) {
            $command[] = $path;
        }

        return $command;
    }
}
