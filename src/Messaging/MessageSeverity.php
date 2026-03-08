<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Messaging;

enum MessageSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
