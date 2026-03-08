<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Messaging;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class StreamingOutputCollector implements OutputCollector
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void
    {
        $this->output->write($text);
    }

    public function writeError(string $text): void
    {
        if ($this->output instanceof ConsoleOutputInterface) {
            $this->output->getErrorOutput()->write($text);
        } else {
            $this->output->write($text);
        }
    }
}