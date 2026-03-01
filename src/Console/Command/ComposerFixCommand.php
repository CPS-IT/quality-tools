<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Exception\FileSystemException;
use Cpsit\QualityTools\Service\ErrorHandler;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function sprintf;

final class ComposerFixCommand extends BaseCommand implements ToolCommandInterface
{
    public const string TOOL_NAME = 'composer-normalize';

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:composer')
            ->setDescription('Run composer-normalize to format composer.json files')
            ->setHelp(
                'This command runs composer-normalize to format composer.json files according ' .
                'to normalized standards. This will modify your composer.json file! Use --path ' .
                'to target specific directories.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                $filesystemService = $this->getFilesystemService();
                if (!$filesystemService->directoryExists($customPath)) {
                    throw new FileSystemException(sprintf('Target path does not exist or is not a directory: %s', $customPath));
                }
                $targetPaths = [$filesystemService->realpath($customPath)];
            } else {
                // Use resolved paths from configuration - check all paths for composer.json files
                $targetPaths = $this->getResolvedPathsForTool($input, 'composer');
            }

            $totalExitCode = 0;
            $foundFiles = 0;

            foreach ($targetPaths as $targetPath) {
                $composerJsonPath = $targetPath . '/composer.json';

                // Check if composer.json exists in this path
                $filesystemService = $this->getFilesystemService();
                if (!$filesystemService->fileExists($composerJsonPath)) {
                    if ($output->isVerbose()) {
                        $output->writeln(sprintf('<comment>No composer.json found at: %s</comment>', $targetPath));
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

                $command = [
                    $composerExecutable,
                    'normalize',
                    $composerJsonPath,
                ];

                $output->writeln(sprintf('<comment>Normalizing composer.json: %s</comment>', $composerJsonPath));

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
        } catch (Throwable $e) {
            // Use the same error handler as AbstractToolCommand for consistency
            $errorHandler = new ErrorHandler();
            return $errorHandler->handleException($e, $output, $output->isVerbose());
        }
    }
}
