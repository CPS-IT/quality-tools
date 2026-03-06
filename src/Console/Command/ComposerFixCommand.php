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

            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->fileExists($composerJsonPath)) {
                if ($output->isVerbose()) {
                    $output->writeln(\sprintf('<comment>No composer.json found at: %s</comment>', $targetPath));
                }
                continue;
            }

            ++$foundFiles;

            $output->writeln(\sprintf('<comment>Normalizing composer.json: %s</comment>', $composerJsonPath));

            $commands[] = [
                $this->resolveComposerExecutable(),
                'normalize',
                $composerJsonPath,
            ];
        }

        if ($foundFiles === 0) {
            $output->writeln('<comment>No composer.json files found in any of the configured paths</comment>');

            return [];
        }

        return $commands[0] ?? [];
    }

    #[\Override]
    protected function resolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return '';
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPaths = $this->resolveTargetPaths($input, $output);

        $totalExitCode = 0;
        $foundFiles = 0;

        foreach ($targetPaths as $targetPath) {
            $composerJsonPath = $targetPath . '/composer.json';

            $filesystemService = $this->getFilesystemService();
            if (!$filesystemService->fileExists($composerJsonPath)) {
                if ($output->isVerbose()) {
                    $output->writeln(\sprintf('<comment>No composer.json found at: %s</comment>', $targetPath));
                }
                continue;
            }

            ++$foundFiles;

            $command = [
                $this->resolveComposerExecutable(),
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

    /**
     * Resolve the composer executable path.
     *
     * Prefers vendor/bin/composer if it exists and is executable,
     * falls back to system composer.
     */
    private function resolveComposerExecutable(): string
    {
        try {
            $vendorComposer = $this->getVendorBinPath() . '/composer';
            if (is_file($vendorComposer) && is_executable($vendorComposer) && $this->isComposerWrapperFunctional($vendorComposer)) {
                return $vendorComposer;
            }
        } catch (\Throwable) {
            // Fall through to system composer
        }

        return 'composer';
    }

    /**
     * Check whether the vendor/bin/composer wrapper can actually run.
     *
     * Composer generates a wrapper that hardcodes the path to composer.phar.
     * In CI where build and test run in separate containers, this path may not exist.
     */
    private function isComposerWrapperFunctional(string $wrapperPath): bool
    {
        $content = @file_get_contents($wrapperPath);
        if ($content === false) {
            return false;
        }

        // Check for the common pattern: the wrapper references a .phar file
        if (preg_match('#([\'"]?)(/[^\'"\s]+composer\.phar)\1#', $content, $matches)) {
            return file_exists($matches[2]);
        }

        // If we can't detect the pattern, assume it works
        return true;
    }
}
