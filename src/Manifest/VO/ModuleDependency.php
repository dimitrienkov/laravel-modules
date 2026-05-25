<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

final readonly class ModuleDependency
{
    public function __construct(
        public string $name,
        public string $constraint,
    ) {
    }
}
