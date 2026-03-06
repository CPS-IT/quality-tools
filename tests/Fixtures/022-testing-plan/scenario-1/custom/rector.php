<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

/**
 * Custom rector configuration for Scenario 1.
 *
 * Installed at: custom/rector.php (relative to project root)
 * This is a simplified config that only applies TYPO3 13 upgrades
 * and void return type rules to the packages directory.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../packages',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);