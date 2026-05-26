<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

final readonly class PathNormalizer
{
    public static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    public static function resolveAbsolute(string $path, string $basePath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $basePath . '/' . trim($path, '/\\');
    }
}
