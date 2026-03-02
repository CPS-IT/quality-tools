<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lint:rector',
    description: 'Run Rector in dry-run mode to analyze code without making changes',
    help: 'This command runs Rector in dry-run mode to show what changes would be made without actually modifying your code files. Use --config to specify a custom configuration file or --path to target specific directories.',
)]
class RectorLintCommand extends AbstractToolCommand implements ToolCommandInterface
{
    public const TOOL_NAME = 'rector';

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
    }

    public function getToolName(): string
    {
        return self::TOOL_NAME;
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
