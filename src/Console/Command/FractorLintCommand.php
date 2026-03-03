<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lint:fractor',
    description: 'Run Fractor in dry-run mode to analyze TypoScript and code without making changes',
    help: 'This command runs Fractor in dry-run mode to show what TypoScript and code ' .
          'changes would be made without actually modifying your files. Use --config ' .
          'to specify a custom configuration file or --path to target specific directories.',
)]
final class FractorLintCommand extends AbstractToolCommand implements ToolCommandInterface
{
    use FractorCommandTrait;

    public function __construct(ConfigurationLoaderInterface $configurationLoader)
    {
        parent::__construct($configurationLoader);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
    }

    #[\Override]
    protected function resolveTargetPaths(InputInterface $input, OutputInterface $output): array
    {
        return $this->resolveFractorTargetPaths($input, $output);
    }

    #[\Override]
    protected function executePreProcessingHooks(InputInterface $input, OutputInterface $output, array $targetPaths): void
    {
        $this->executeFractorPreProcessingHooks($input, $output, $targetPaths);
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        return $this->buildFractorCommand($input, $configPath, $targetPaths, true);
    }

    #[\Override]
    protected function executePostProcessingHooks(InputInterface $input, OutputInterface $output, int $exitCode): void
    {
        $this->executeFractorPostProcessingHooks($output, $exitCode);
    }
}
