<?php

/**
 * See https://github.com/andreaswolf/fractor/blob/main/README.md for more information
 */

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\FractorTypoScript\Configuration\TypoScriptProcessorOption;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;
use Helmich\TypoScriptParser\Parser\Printer\PrettyPrinterConfiguration;

$installPath = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);

// Get paths from environment variable if set (for dynamic configuration)
$dynamicPaths = $_SERVER['QT_DYNAMIC_PATHS'] ?? null;
$scanPaths = [];

if ($dynamicPaths !== null) {
    // Parse dynamic paths from environment variable (JSON encoded)
    $decodedPaths = json_decode($dynamicPaths, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPaths)) {
        $scanPaths = $decodedPaths;
    }
}

// Fallback to default paths if no dynamic paths provided
if (empty($scanPaths)) {
    $scanPaths = [
        $installPath . '/config/sites/',
        $installPath . '/packages/',
    ];
}

return FractorConfiguration::configure()
    ->withPaths($scanPaths)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withOptions([
        TypoScriptProcessorOption::INDENT_SIZE => 2,
        TypoScriptProcessorOption::INDENT_CHARACTER => PrettyPrinterConfiguration::INDENTATION_STYLE_SPACES,
        TypoScriptProcessorOption::ADD_CLOSING_GLOBAL => true,
        TypoScriptProcessorOption::INCLUDE_EMPTY_LINE_BREAKS => true,
        TypoScriptProcessorOption::INDENT_CONDITIONS => true,
    ]);
