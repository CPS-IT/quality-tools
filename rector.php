<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(30)
    ->withDeadCodeLevel(30)
    ->withCodeQualityLevel(30)
    ->withSkip([
        // Skip NewInInitializerRector for TemporaryFile as we have planned Symfony DI injection
        NewInInitializerRector::class => [
            __DIR__ . '/src/Service/TemporaryFile.php',
        ],
    ]);
