<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Command;

use Cpsit\QualityTools\Console\Runner\ConfigValidateRunner;
use Cpsit\QualityTools\Console\Runner\DTO\ConfigValidateRequest;
use Cpsit\QualityTools\Messaging\MessageSeverity;
use Cpsit\QualityTools\Messaging\StreamingOutputCollector;
use Cpsit\QualityTools\Service\ErrorHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigValidateCommand extends Command
{
    public function __construct(
        private readonly ConfigValidateRunner $runner,
        string $name,
        string $description,
        string $help,
    ) {
        parent::__construct($name);
        $this->setDescription($description);
        $this->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $request = new ConfigValidateRequest();

            $description = $this->runner->describe($request);

            if ($output->isVerbose() && $description->configPath !== '') {
                $output->writeln(\sprintf('<info>Configuration file: %s</info>', $description->configPath));
            }

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
