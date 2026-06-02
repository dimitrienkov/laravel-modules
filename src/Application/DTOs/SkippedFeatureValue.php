<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class SkippedFeatureValue
{
    public function __construct(
        public string $key,
        public string $reason,
    ) {}
}
