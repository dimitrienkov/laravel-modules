<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;

final readonly class FeatureValueNormalizer
{
    /**
     * @param array<int, string> $options
     */
    public static function normalize(
        string $key,
        FeatureType $type,
        mixed $value,
        ?int $min,
        ?int $max,
        array $options,
        string $manifestPath,
        string $valueName,
    ): bool|int|string {
        return match ($type) {
            FeatureType::Boolean => self::normalizeBool($key, $value, $manifestPath, $valueName),
            FeatureType::Integer => self::normalizeInt($key, $value, $min, $max, $manifestPath, $valueName),
            FeatureType::String => self::normalizeString($key, $value, $min, $max, $manifestPath, $valueName),
            FeatureType::Enum => self::normalizeEnum($key, $value, $options, $manifestPath, $valueName),
        };
    }

    private static function normalizeBool(
        string $key,
        mixed $value,
        string $manifestPath,
        string $valueName,
    ): bool {
        if (! \is_bool($value)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be a boolean.",
            );
        }

        return $value;
    }

    private static function normalizeInt(
        string $key,
        mixed $value,
        ?int $min,
        ?int $max,
        string $manifestPath,
        string $valueName,
    ): int {
        if (! \is_int($value)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be an integer.",
            );
        }

        if ($min !== null && $value < $min) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be greater than or equal to {$min}.",
            );
        }

        if ($max !== null && $value > $max) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be less than or equal to {$max}.",
            );
        }

        return $value;
    }

    private static function normalizeString(
        string $key,
        mixed $value,
        ?int $min,
        ?int $max,
        string $manifestPath,
        string $valueName,
    ): string {
        if (! \is_string($value)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be a string.",
            );
        }

        $length = mb_strlen($value, 'UTF-8');
        if ($min !== null && $length < $min) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} length must be greater than or equal to {$min}.",
            );
        }

        if ($max !== null && $length > $max) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} length must be less than or equal to {$max}.",
            );
        }

        return $value;
    }

    /**
     * @param array<int, string> $options
     */
    private static function normalizeEnum(
        string $key,
        mixed $value,
        array $options,
        string $manifestPath,
        string $valueName,
    ): string {
        if (! \is_string($value) || ! \in_array($value, $options, true)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be one of: " . implode(', ', $options) . '.',
            );
        }

        return $value;
    }
}
