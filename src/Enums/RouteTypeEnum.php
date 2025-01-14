<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Enums;

enum RouteTypeEnum: string
{
    case API = 'api';
    case WEB = 'web';
    case INERTIA = 'inertia';
    case ALL = 'all';

    public static function getAvailableTypes(): array
    {
        return array_filter(self::cases(), static fn (self $case): bool => $case !== self::ALL);
    }
}
