<?php

declare(strict_types=1);

$installPath = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();

// Default scan paths for TYPO3 v13
$scanPaths = [
    $installPath . '/config/system',
    $installPath . '/packages',
];

// Add all scan paths to the finder
foreach ($scanPaths as $path) {
    if (is_dir($path)) {
        $config->getFinder()->in($path);
    }
}

return $config;
