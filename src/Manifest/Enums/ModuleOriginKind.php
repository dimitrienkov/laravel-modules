<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Enums;

enum ModuleOriginKind: string
{
    case Local = 'local';
    case Zip = 'zip';
}
