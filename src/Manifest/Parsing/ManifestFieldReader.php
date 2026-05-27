<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class ManifestFieldReader
{
    private const string MODULE_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param array<string, mixed> $data
     * @param array<string, true>  $allowedKeys
     */
    public static function assertAllowedKeys(
        array $data,
        array $allowedKeys,
        string $context,
        string $manifestPath,
    ): void {
        foreach (array_keys($data) as $key) {
            if (! isset($allowedKeys[$key])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "{$context} contains unknown key [{$key}].",
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function requiredObject(
        array $data,
        string $key,
        string $manifestPath,
    ): array {
        $value = $data[$key] ?? null;

        if (! \is_array($value) || ($value !== [] && array_is_list($value))) {
            throw InvalidManifestException::forPath($manifestPath, "{$key} must be an object.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function requiredString(
        array $data,
        string $key,
        string $context,
        string $manifestPath,
    ): string {
        $value = $data[$key] ?? null;

        if (! \is_string($value) || trim($value) === '') {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$context}.{$key} must be a non-empty string.",
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(
        array $data,
        string $key,
        string $context,
        string $manifestPath,
    ): ?string {
        if (! \array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        if (! \is_string($data[$key])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$context}.{$key} must be a string.",
            );
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function requiredBool(
        array $data,
        string $key,
        string $context,
        string $manifestPath,
    ): bool {
        if (! \array_key_exists($key, $data) || ! \is_bool($data[$key])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$context}.{$key} must be a boolean.",
            );
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function requiredInt(
        array $data,
        string $key,
        string $context,
        string $manifestPath,
    ): int {
        if (! \array_key_exists($key, $data) || ! \is_int($data[$key])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$context}.{$key} must be an integer.",
            );
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalInt(
        array $data,
        string $key,
        string $context,
        string $manifestPath,
    ): ?int {
        if (! \array_key_exists($key, $data)) {
            return null;
        }

        if (! \is_int($data[$key])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$context}.{$key} must be an integer.",
            );
        }

        return $data[$key];
    }

    public static function assertModuleName(
        string $name,
        string $field,
        string $manifestPath,
    ): void {
        if (! preg_match(self::MODULE_NAME_PATTERN, $name)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "{$field} must be lowercase snake_case (a-z, 0-9, underscore, starting with a letter).",
            );
        }
    }
}
