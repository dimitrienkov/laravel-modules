<?php

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    'paths' => [
        'directories' => [
            'app/Modules',
            'app/Integrations',
            'app/Subsystems',
        ],
    ],
    'routing' => [
        'types' => [
            'api' => [
                'prefix' => 'api',
                'middleware' => [SubstituteBindings::class, ConvertEmptyStringsToNull::class, 'api'],
            ],
            'web' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
            'inertia' => [
                'prefix' => null,
                'middleware' => ['web'],
            ],
        ],
    ],
];
