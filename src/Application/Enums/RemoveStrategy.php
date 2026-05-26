<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

enum RemoveStrategy: string
{
    case Backup = 'backup';
    case Permanent = 'permanent';
}
