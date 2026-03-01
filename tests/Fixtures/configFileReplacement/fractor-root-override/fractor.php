<?php

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;

return static function (FractorConfiguration $configuration): void {
    $configuration->paths([
        'custom-fractor-root-path/',
    ]);
    $configuration->fileExtensions(['typoscript', 'tsconfig']);
};
