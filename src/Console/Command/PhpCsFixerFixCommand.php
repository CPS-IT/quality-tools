<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PhpCsFixerFixCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('fix:php-cs-fixer')
            ->setDescription('Run PHP CS Fixer to automatically fix code style issues')
            ->setHelp(
                'This command runs PHP CS Fixer to automatically fix code style issues. ' .
                'This will modify your code files! Use --config to specify a custom ' .
                'configuration file or --path to target specific directories.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getOption('show-optimization')) {
                $this->showOptimizationDetails($input, $output, 'php-cs-fixer');
            }

            $configPath = $this->resolveConfigPath('php-cs-fixer.php', $input->getOption('config'));
            $targetPath = $this->getTargetPath($input);

            $command = [
                $this->getVendorBinPath() . '/php-cs-fixer',
                'fix',
                '--config=' . $configPath,
            ];

            // Enable parallel processing if beneficial
            if ($this->shouldEnableParallelProcessing($input, $output)) {
                $command[] = '--using-cache=yes';
            }

            $command[] = $targetPath;

            // Get optimal memory limit only if optimization is enabled
            $memoryLimit = null;
            if (!$this->isOptimizationDisabled($input)) {
                $memoryLimit = $this->getOptimalMemoryLimit($input, $output, 'php-cs-fixer');
            }

            return $this->executeProcess($command, $input, $output, $memoryLimit);

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}
