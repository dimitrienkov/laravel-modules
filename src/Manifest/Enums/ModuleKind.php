<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Enums;

enum ModuleKind: string
{
    case Module = 'module';
    case Subsystem = 'subsystem';
    case Integration = 'integration';
}
