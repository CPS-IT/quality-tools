<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

/**
 * CLI override rector config for Scenario 4.
 *
 * Installed at: cli/rector.php (relative to project root)
 * Used via: vendor/bin/qt lint:rector --config=cli/rector.php
 * This has the highest precedence and overrides both YAML config_file
 * and auto-discovered rector.php.
 *
 * Precedence: CLI --config (this) > YAML config_file > auto-discovered
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../packages',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
