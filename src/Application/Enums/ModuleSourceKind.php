<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Enums;

enum ModuleSourceKind: string
{
    case Directory = 'directory';
    case Zip = 'zip';
}
