<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\Runner\ConfigInitRunner;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigInitRequest;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use Cpsit\QualityTools\Service\ErrorHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigInitCommand extends Command
{
    public function __construct(
        private readonly ConfigInitRunner $runner,
        string $name,
        string $description,
        string $help,
    ) {
        parent::__construct($name);
        $this->setDescription($description);
        $this->setHelp($help);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED,
                'Configuration template: default, typo3-extension, typo3-site-package, typo3-distribution',
                'default',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing configuration file',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $request = new ConfigInitRequest(
                template: $input->getOption('template'),
                force: (bool) $input->getOption('force'),
            );

            $collector = new StreamingOutputCollector($output);
            $result = $this->runner->run($request, $collector);

            // Always show errors and warnings; info messages only in verbose mode
            foreach ($result->messages as $message) {
                match ($message->severity) {
                    MessageSeverity::Error => $output->writeln('<error>' . $message->text . '</error>'),
                    MessageSeverity::Warning => $output->writeln('<comment>' . $message->text . '</comment>'),
                    MessageSeverity::Info => $output->isVerbose()
                        ? $output->writeln('<info>' . $message->text . '</info>')
                        : null,
                };
            }

            return $result->exitCode;
        } catch (\Throwable $e) {
            return (new ErrorHandler())->handleException($e, $output, $output->isVerbose());
        }
    }
}
