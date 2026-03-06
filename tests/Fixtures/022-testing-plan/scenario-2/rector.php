<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

/**
 * Auto-discoverable rector.php in project root for Scenario 2.
 *
 * Installed at: rector.php (project root)
 * This simulates a rector.php that would be found at the project root
 * via auto-discovery when no config_file is set in .quality-tools.yaml.
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
    ])
    ->withPHPStanConfigs([Typo3Option::PHPSTAN_FOR_RECTOR_PATH])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withConfiguredRule(ExtEmConfRector::class, [
        ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.2.0-8.3.99',
        ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '13.4.0-13.4.99',
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ]);