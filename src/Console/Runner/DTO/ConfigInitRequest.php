<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner\DTO;

final readonly class ConfigInitRequest
{
    public function __construct(
        public string $template = 'default',
        public bool $force = false,
    ) {
    }
}
