<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCsFixerFixCommand extends AbstractToolCommand implements ToolCommandInterface
{
    public const string TOOL_NAME = 'php-cs-fixer';

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'php-cs-fixer.php';
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:php-cs-fixer')
            ->setDescription('Run PHP CS Fixer to automatically fix code style issues')
            ->setHelp(
                'This command runs PHP CS Fixer to automatically fix code style issues. ' .
                'This will modify your code files! Use --config to specify a custom ' .
                'configuration file or --path to target specific directories.',
            );
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
            '--config=' . $configPath,
        ];

        // Enable parallel processing if beneficial
        if ($this->shouldEnableParallelProcessing($input, 'php-cs-fixer')) {
            $command[] = '--using-cache=yes';
        }

        // Add target paths if provided
        if (!empty($targetPaths)) {
            foreach ($targetPaths as $path) {
                $command[] = $path;
            }
        }

        return $command;
    }
}
