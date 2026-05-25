<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class RemoveModuleResult
{
    public function __construct(
        public string $name,
        public string $removedPath,
        public ?string $backupPath,
    ) {
    }
}
