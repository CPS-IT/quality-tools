<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\ToolRunner;

final class ToolRunnerRegistry
{
    /** @var array<string, ToolRunnerInterface> */
    private array $runners = [];

    /**
     * @param iterable<ToolRunnerInterface> $runners
     */
    public function __construct(iterable $runners)
    {
        foreach ($runners as $runner) {
            foreach ($runner->supportedTools() as $tool) {
                $this->runners[$tool] = $runner;
            }
        }
    }

    public function get(string $toolName): ToolRunnerInterface
    {
        return $this->runners[$toolName]
            ?? throw new \InvalidArgumentException(\sprintf('No runner registered for tool: %s', $toolName));
    }

    public function has(string $toolName): bool
    {
        return isset($this->runners[$toolName]);
    }

    /**
     * @return list<string>
     */
    public function getRegisteredTools(): array
    {
        return array_keys($this->runners);
    }
}
