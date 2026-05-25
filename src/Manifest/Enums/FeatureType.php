<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Enums;

enum FeatureType: string
{
    case Boolean = 'bool';
    case Integer = 'int';
    case String = 'string';
    case Enum = 'enum';
}
