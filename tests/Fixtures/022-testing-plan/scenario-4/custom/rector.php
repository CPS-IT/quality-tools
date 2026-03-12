<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

/**
 * YAML-configured custom rector config for Scenario 4.
 *
 * Installed at: custom/rector.php (relative to project root)
 * Referenced via config_file: "custom/rector.php" in .quality-tools.yaml.
 * Used when no CLI --config option is provided.
 *
 * Precedence: CLI --config > YAML config_file (this) > auto-discovered
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../packages',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
