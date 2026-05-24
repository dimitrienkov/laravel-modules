<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Contracts;

interface NamespaceResolverInterface
{
    public function resolve(string $modulePath): string;
}
