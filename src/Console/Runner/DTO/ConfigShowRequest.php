<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Console\Runner\DTO;

final readonly class ConfigShowRequest
{
    public function __construct(
        public string $format = 'yaml',
    ) {
    }
}
