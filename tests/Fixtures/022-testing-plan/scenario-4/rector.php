<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

/**
 * Auto-discoverable rector.php in project root for Scenario 4.
 *
 * Installed at: rector.php (project root)
 * This config should be IGNORED because .quality-tools.yaml specifies
 * config_file: "custom/rector.php" which takes precedence over auto-discovery.
 *
 * Precedence: CLI --config > YAML config_file > auto-discovered
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/config/system',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
