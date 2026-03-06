<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fix:composer',
    description: 'Run composer-normalize to format composer.json files',
    help: 'This command runs composer-normalize to format composer.json files according to normalized standards. This will modify your composer.json file! Use --path to target specific directories.',
)]
final class ComposerFixCommand extends AbstractToolCommand implements ToolCommandInterface
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
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        $foundFiles = 0;
        $commands = [];

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
            // Check if composer exists in vendor/bin (for tests), otherwise use system composer
            $composerExecutable = 'composer';
            $vendorComposer = $this->getVendorBinPath() . '/composer';
            if ($filesystemService->fileExists($vendorComposer)) {
                $composerExecutable = $vendorComposer;
            }

            $output->writeln(\sprintf('<comment>Normalizing composer.json: %s</comment>', $composerJsonPath));

            // Store for execution - we'll handle multiple files differently
            $commands[] = [
                $composerExecutable,
                'normalize',
                $composerJsonPath,
            ];
        }

        if ($foundFiles === 0) {
            $output->writeln('<comment>No composer.json files found in any of the configured paths</comment>');

            // Return empty command to trigger error
            return [];
        }

        // For now, return the first command (we'll need to handle multiple files differently)
        return $commands[0] ?? [];
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
                $composerJsonPath,
            ];

            $output->writeln(\sprintf('<comment>Normalizing composer.json: %s</comment>', $composerJsonPath));

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
