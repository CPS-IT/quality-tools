<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner\DTO;

/**
 * Describes what a command runner will do for a given request,
 * without actually executing the operation.
 */
final readonly class CommandRunDescription
{
    /**
     * @param array<string, scalar> $info
     */
    public function __construct(
        public string $operationName,
        public string $configPath,
        public array $info = [],
    ) {
    }
}
