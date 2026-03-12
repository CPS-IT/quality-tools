<?php

declare(strict_types=1);

/**
 * Custom PHP CS Fixer configuration for Scenario 5.
 *
 * One of three custom tool configs in this scenario, testing that
 * multiple tools can each have their own custom config_file.
 * Scans only the packages directory.
 */
$installPath = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();

$scanPaths = [
    $installPath . '/packages',
];

foreach ($scanPaths as $path) {
    if (is_dir($path)) {
        $config->getFinder()->in($path);
    }
}

return $config;
