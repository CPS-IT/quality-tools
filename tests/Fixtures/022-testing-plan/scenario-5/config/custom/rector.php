<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

/**
 * Custom rector configuration for Scenario 5.
 *
 * Installed at: config/custom/rector.php (relative to project root)
 * One of three custom tool configs in this scenario, testing that
 * multiple tools can each have their own custom config_file.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../packages',
        __DIR__ . '/../../config/system',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);