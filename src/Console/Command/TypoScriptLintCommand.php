<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lint:typoscript',
    description: 'Run TypoScript Lint to check TypoScript files for syntax errors',
    help: 'This command runs TypoScript Lint to check TypoScript files for syntax errors and coding standard violations. Use --config to specify a custom configuration file or --path to target specific directories.',
)]
final class TypoScriptLintCommand extends AbstractToolCommand implements ToolCommandInterface
{
    public const string TOOL_NAME = 'typoscript-lint';

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'typoscript-lint.yml';
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        $command = [
            $this->getVendorBinPath() . '/typoscript-lint',
            '-c',
            $configPath,
        ];

        // Add target paths if provided
        if (!empty($targetPaths)) {
            foreach ($targetPaths as $path) {
                $command[] = $path;
            }
        } else {
            if ($output->isVerbose()) {
                $output->writeln('<comment>Using configuration file path discovery (packages/**/Configuration/TypoScript)</comment>');
            }
        }

        return $command;
    }
}
