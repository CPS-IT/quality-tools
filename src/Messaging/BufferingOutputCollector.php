<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Messaging;

final class BufferingOutputCollector implements OutputCollector
{
    /** @var list<array{text: string, severity: MessageSeverity}> */
    private array $collected = [];

    /** @var list<string> */
    private array $errors = [];

    public function write(string $text, MessageSeverity $severity = MessageSeverity::Info): void
    {
        $this->collected[] = ['text' => $text, 'severity' => $severity];
    }

    public function writeError(string $text): void
    {
        $this->errors[] = $text;
    }

    /**
     * @return list<array{text: string, severity: MessageSeverity}>
     */
    public function getCollected(): array
    {
        return $this->collected;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getOutput(): string
    {
        return implode('', array_column($this->collected, 'text'));
    }

    public function getErrorOutput(): string
    {
        return implode('', $this->errors);
    }

    public function reset(): void
    {
        $this->collected = [];
        $this->errors = [];
    }
}