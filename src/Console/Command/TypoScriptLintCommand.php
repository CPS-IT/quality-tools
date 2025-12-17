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
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getProjectRoot() . '/vendor/bin/typoscript-lint',
                '-c',
                $configPath,
                '--path',
                $targetPath
            ];

            return $this->executeProcess($command, $input, $output);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
