<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

/**
 * Alternative rector configuration for Scenario 3.
 *
 * Installed at: alternative/rector.php (relative to project root)
 * Used via CLI override: vendor/bin/qt lint:rector --config=alternative/rector.php
 * This minimal config only applies TYPO3 13 upgrades to packages.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../packages',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
