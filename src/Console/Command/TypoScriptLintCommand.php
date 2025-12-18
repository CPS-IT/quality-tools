<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TypoScriptLintCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('lint:typoscript')
            ->setDescription('Run TypoScript Lint to check TypoScript files for syntax errors')
            ->setHelp(
                'This command runs TypoScript Lint to check TypoScript files for syntax errors ' .
                'and coding standard violations. Use --config to specify a custom configuration ' .
                'file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $configPath = $this->resolveConfigPath('typoscript-lint.yml', $input->getOption('config'));

            $command = [
                $this->getVendorBinPath() . '/typoscript-lint',
                '-c',
                $configPath
            ];

            // If user specified a custom path, add it as positional argument
            $customPath = $input->getOption('path');
            if ($customPath !== null) {
                if (!is_dir($customPath)) {
                    throw new \InvalidArgumentException(
                        sprintf('Target path does not exist or is not a directory: %s', $customPath)
                    );
                }
                $command[] = realpath($customPath);
                $output->writeln(sprintf('<comment>Analyzing custom path: %s</comment>', $customPath));
            } else {
                $output->writeln('<comment>Using configuration file path discovery (packages/**/Configuration/TypoScript)</comment>');
            }

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
