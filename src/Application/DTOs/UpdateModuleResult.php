<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

use DimitrienkoV\LaravelModules\Manifest\VO\Version;

final readonly class UpdateModuleResult
{
    /**
     * @param list<SkippedFeatureValue> $skippedValues
     */
    public function __construct(
        public string $name,
        public Version $oldVersion,
        public Version $newVersion,
        public array $skippedValues,
        public string $path,
    ) {}
}
