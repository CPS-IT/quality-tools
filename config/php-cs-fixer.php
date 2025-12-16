<?php

declare(strict_types=1);

$installPath = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()->in($installPath . '/config/system');
$config->getFinder()->in($installPath . '/packages');
return $config;
