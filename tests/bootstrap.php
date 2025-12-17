<?php

declare(strict_types=1);

// Find the autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

$autoloadFile = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloadFile = $path;
        break;
    }
}

if ($autoloadFile === null) {
    throw new RuntimeException('Composer autoloader not found. Please run "composer install".');
}

require_once $autoloadFile;

// Set timezone to avoid warnings
date_default_timezone_set('UTC');

// Ensure test mode is enabled
putenv('QT_TEST_MODE=true');
