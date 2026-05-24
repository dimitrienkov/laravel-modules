<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Enums;

enum FeatureType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case String = 'string';
    case Enum = 'enum';
}
