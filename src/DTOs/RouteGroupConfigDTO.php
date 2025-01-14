<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\DTOs;

readonly class RouteGroupConfigDTO
{
    public function __construct(
        public ?string $prefix = '',
        public string|array $middleware = [],
    ) {}
}
