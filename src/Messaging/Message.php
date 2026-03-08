<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Messaging;

final readonly class Message
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public MessageSeverity $severity,
        public string $text,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Info, $text, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Warning, $text, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $text, array $context = []): self
    {
        return new self(MessageSeverity::Error, $text, $context);
    }
}
