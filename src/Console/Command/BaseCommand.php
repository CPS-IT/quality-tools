<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class BaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Override default configuration file path'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Specify custom target paths (defaults to project root)'
            );
    }

    protected function getProjectRoot(): string
    {
        $application = $this->getApplication();

        if (!$application instanceof QualityToolsApplication) {
            throw new \RuntimeException('Command must be run within QualityToolsApplication');
        }

        return $application->getProjectRoot();
    }

    protected function resolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        if ($customConfigPath !== null) {
            if (!file_exists($customConfigPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Custom configuration file not found: %s', $customConfigPath)
                );
            }
            return realpath($customConfigPath);
        }

        $vendorPath = $this->findVendorPath();
        $defaultConfigPath = $vendorPath . '/cpsit/quality-tools/config/' . $configFile;

        if (!file_exists($defaultConfigPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Default configuration file not found: %s. Please ensure cpsit/quality-tools is properly installed.',
                    $defaultConfigPath
                )
            );
        }

        return $defaultConfigPath;
    }

    protected function getVendorBinPath(): string
    {
        return $this->findVendorPath() . '/bin';
    }

    private function findVendorPath(): string
    {
        $projectRoot = $this->getProjectRoot();
        
        // Try common vendor directory locations
        $vendorPaths = [
            $projectRoot . '/app/vendor',  // TYPO3 with app/vendor structure
            $projectRoot . '/vendor',      // Standard composer structure
        ];

        foreach ($vendorPaths as $vendorPath) {
            if (is_dir($vendorPath) && is_dir($vendorPath . '/cpsit/quality-tools')) {
                return $vendorPath;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Could not find vendor directory with cpsit/quality-tools package. Checked: %s',
                implode(', ', $vendorPaths)
            )
        );
    }

    protected function executeProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $process = new Process($command, $this->getProjectRoot());

        // Handle verbose mode using Symfony's built-in verbose levels
        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>Executing: %s</info>', $process->getCommandLine()));
        }

        $process->run(function (string $type, string $buffer) use ($output): void {
            // Forward output from the process (Symfony handles quiet mode automatically)
            if ($type === Process::ERR) {
                $output->getErrorOutput()->write($buffer);
            } else {
                $output->write($buffer);
            }
        });

        return $process->getExitCode() ?? 1;
    }

    protected function getTargetPath(InputInterface $input): string
    {
        $customPath = $input->getOption('path');

        if ($customPath !== null) {
            if (!is_dir($customPath)) {
                throw new \InvalidArgumentException(
                    sprintf('Target path does not exist or is not a directory: %s', $customPath)
                );
            }
            return realpath($customPath);
        }

        return $this->getProjectRoot();
    }
}
