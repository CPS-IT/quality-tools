<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerLintCommand extends AbstractToolCommand implements ToolCommandInterface
{
    public const string TOOL_NAME = 'composer-normalize';

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    protected function getDefaultConfigFileName(): string
    {
        // Composer normalize doesn't use a config file
        return '';
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:composer')
            ->setDescription('Run composer-normalize in dry-run mode to check composer.json formatting')
            ->setHelp(
                'This command runs composer-normalize in dry-run mode to check if composer.json ' .
                'files are properly formatted without making changes. Use --path to target ' .
                'specific directories.',
            );
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        // This method isn't used since we override execute()
        return [];
    }

    #[\Override]
    protected function resolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        // Composer normalize doesn't use a config file, return empty
        return '';
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // For composer normalize, we need custom handling for multiple files
        $targetPaths = $this->resolveTargetPaths($input, $output);
        
        $totalExitCode = 0;
        $foundFiles = 0;

        foreach ($targetPaths as $targetPath) {
            $composerJsonPath = $targetPath . '/composer.json';

            // Check if composer.json exists in this path
            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->fileExists($composerJsonPath)) {
                if ($output->isVerbose()) {
                    $output->writeln(\sprintf('<comment>No composer.json found at: %s</comment>', $targetPath));
                }
                continue;
            }

            ++$foundFiles;

            // Use composer normalize plugin command
            $composerExecutable = 'composer';
            $vendorComposer = $this->getVendorBinPath() . '/composer';
            if ($filesystemService->fileExists($vendorComposer)) {
                $composerExecutable = $vendorComposer;
            }

            $command = [
                $composerExecutable,
                'normalize',
                '--dry-run',
                '--diff',
                $composerJsonPath,
            ];

            $output->writeln(\sprintf('<comment>Checking composer.json normalization: %s</comment>', $composerJsonPath));

            $exitCode = $this->executeProcess($command, $input, $output);
            if ($exitCode !== 0) {
                $totalExitCode = $exitCode;
            }
        }

        if ($foundFiles === 0) {
            $output->writeln('<comment>No composer.json files found in any of the configured paths</comment>');
            return 1;
        }

        return $totalExitCode;
    }
}