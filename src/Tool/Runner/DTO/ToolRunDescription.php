<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tool\Runner\DTO;

use Cpsit\QualityTools\Utility\ProjectMetrics;

/**
 * Describes what a tool runner will do for a given request,
 * without actually executing the tool.
 */
final readonly class ToolRunDescription
{
    /**
     * @param list<string>          $targetPaths
     * @param array<string, scalar> $toolSpecificInfo
     */
    public function __construct(
        public string $toolName,
        public string $configPath,
        public array $targetPaths,
        public ?ProjectMetrics $metrics = null,
        public ?string $memoryLimit = null,
        public array $toolSpecificInfo = [],
    ) {
    }
}
