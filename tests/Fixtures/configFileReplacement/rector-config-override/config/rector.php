<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        'custom-rector-config-path/',
    ]);
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
};
