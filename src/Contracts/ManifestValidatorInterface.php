<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface ManifestValidatorInterface
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest, string $manifestPath): void;
}
