<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class UpdateModuleResult
{
    /**
     * @param list<string> $skippedValues
     */
    public function __construct(
        public string $name,
        public string $oldVersion,
        public string $newVersion,
        public array $skippedValues,
        public string $path,
    ) {
    }
}
