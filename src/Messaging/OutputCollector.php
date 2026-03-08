<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Messaging;

interface OutputCollector
{
    /**
     * Collect a line of tool output.
     */
    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void;

    /**
     * Collect error output from the tool process.
     */
    public function writeError(string $text): void;
}
