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
        $absolute = str_starts_with($path, '/')
            ? $path
            : $basePath . '/' . trim($path, '/\\');

        return self::collapse($absolute);
    }

    private static function collapse(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $segments = explode('/', $normalized);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..' && $result !== [] && end($result) !== '') {
                array_pop($result);

                continue;
            }

            $result[] = $segment;
        }

        return implode('/', $result) ?: '/';
    }
}
