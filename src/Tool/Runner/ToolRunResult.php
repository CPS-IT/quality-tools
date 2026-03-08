<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

use Cpsit\QualityTools\Messaging\Message;
use Cpsit\QualityTools\Messaging\MessageSeverity;

final readonly class ToolRunResult
{
    /**
     * @param list<Message> $messages
     */
    public function __construct(
        public int $exitCode,
        public array $messages = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function hasErrors(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->severity === MessageSeverity::Error) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->severity === MessageSeverity::Warning) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Message>
     */
    public function getMessagesBySeverity(MessageSeverity $severity): array
    {
        return array_values(
            array_filter(
                $this->messages,
                static fn (Message $message): bool => $message->severity === $severity,
            ),
        );
    }
}
