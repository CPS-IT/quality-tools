<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpStanCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:phpstan')
            ->setDescription('Run PHPStan static analysis')
            ->setHelp(
                'This command runs PHPStan static analysis to find bugs in your code without ' .
                'running it. Use --config to specify a custom configuration file, --path to ' .
                'target specific directories, or --level to override the analysis level.'
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Override the analysis level (0-9)'
            )
            ->addOption(
                'memory-limit',
                'm',
                InputOption::VALUE_REQUIRED,
                'Memory limit for analysis (e.g., 1G, 512M)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configPath = $this->resolveConfigPath('phpstan.neon', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getProjectRoot() . '/vendor/bin/phpstan',
                'analyse',
                '--configuration=' . $configPath
            ];

            // Add custom analysis level if specified
            $level = $input->getOption('level');
            if ($level !== null) {
                $command[] = '--level=' . $level;
            }

            // Add memory limit if specified
            $memoryLimit = $input->getOption('memory-limit');
            if ($memoryLimit !== null) {
                $command[] = '--memory-limit=' . $memoryLimit;
            }

            // Add target path
            $command[] = $targetPath;

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
