<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner;

final readonly class ToolRunRequest
{
    /**
     * @param array<string, scalar> $toolOptions Tool-specific options (level, memory-limit, etc.)
     */
    public function __construct(
        public string $toolName,
        public bool $dryRun,
        public ?string $configOverride = null,
        public ?string $pathOverride = null,
        public array $toolOptions = [],
    ) {
    }
}
