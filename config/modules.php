<?php

use App\Http\Middleware\HandleInertiaRequests;
use DimitrienkoV\LaravelModules\Enums\RouteTypeEnum;

return [
    'paths' => [
        'modules' => 'app/Modules',
        'config' => 'Config',
        'routes' => 'Routes',
        'database' => 'Database',
        'migrations' => 'Migrations',
        'factories' => 'Factories',
        'models' => 'Models',
    ],
    'route' => [
        'middlewares' => [
            RouteTypeEnum::API->value => [
                'prefix' => 'api',
                'middleware' => 'api',
            ],
            RouteTypeEnum::WEB->value => [
                'prefix' => null,
                'middleware' => [],
            ],
            RouteTypeEnum::INERTIA->value => [
                'prefix' => null,
                'middleware' => HandleInertiaRequests::class,
            ],
        ],
    ],
];
