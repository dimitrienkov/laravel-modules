<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Parsing;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureDefinition;

final readonly class FeatureDefinitionFactory
{
    private const array ALLOWED_KEYS = [
        'type' => true,
        'default' => true,
        'min' => true,
        'max' => true,
        'options' => true,
        'label' => true,
        'description' => true,
        'group' => true,
    ];

    /**
     * @param array<string, mixed> $definition
     */
    public static function fromArray(string $key, array $definition, string $manifestPath): FeatureDefinition
    {
        if (trim($key) === '') {
            throw InvalidManifestException::forPath($manifestPath, 'settings.schema keys must be non-empty strings.');
        }

        $context = "settings.schema.{$key}";
        ManifestFieldReader::assertAllowedKeys($definition, self::ALLOWED_KEYS, $context, $manifestPath);

        $type = self::parseType($key, $definition, $manifestPath);
        $min = ManifestFieldReader::optionalInt($definition, 'min', $context, $manifestPath);
        $max = ManifestFieldReader::optionalInt($definition, 'max', $context, $manifestPath);

        if ($min !== null && $max !== null && $min > $max) {
            throw InvalidManifestException::forPath($manifestPath, "{$context}.min cannot exceed max.");
        }

        $options = self::parseOptions($key, $type, $definition, $manifestPath);
        self::assertTypeSpecificRules($key, $type, $min, $max, $options, $manifestPath);

        $hasDefault = \array_key_exists('default', $definition);
        $default = $hasDefault
            ? FeatureValueNormalizer::normalize($key, $type, $definition['default'], $min, $max, $options, $manifestPath, 'default')
            : null;

        $label = ManifestFieldReader::optionalString($definition, 'label', $context, $manifestPath);
        $description = ManifestFieldReader::optionalString($definition, 'description', $context, $manifestPath);
        $group = ManifestFieldReader::optionalString($definition, 'group', $context, $manifestPath);

        return new FeatureDefinition(
            key: $key,
            type: $type,
            hasDefault: $hasDefault,
            default: $default,
            min: $min,
            max: $max,
            options: $options,
            label: $label,
            description: $description,
            group: $group,
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function parseType(string $key, array $definition, string $manifestPath): FeatureType
    {
        $type = $definition['type'] ?? null;
        if (! \is_string($type)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.type must be one of: bool, int, string, enum.",
            );
        }

        $featureType = FeatureType::tryFrom($type);
        if ($featureType === null) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.type must be one of: bool, int, string, enum.",
            );
        }

        return $featureType;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<int, string>
     */
    private static function parseOptions(
        string $key,
        FeatureType $type,
        array $definition,
        string $manifestPath,
    ): array {
        if (! \array_key_exists('options', $definition)) {
            if ($type === FeatureType::Enum) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key}.options must be a non-empty string list for enum features.",
                );
            }

            return [];
        }

        if (! \is_array($definition['options']) || ! array_is_list($definition['options'])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.options must be a list of strings.",
            );
        }

        $options = [];
        $seen = [];
        foreach ($definition['options'] as $option) {
            if (! \is_string($option) || $option === '') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key}.options entries must be non-empty strings.",
                );
            }

            if (isset($seen[$option])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key}.options contains duplicate entry [{$option}].",
                );
            }

            $seen[$option] = true;
            $options[] = $option;
        }

        if ($type === FeatureType::Enum && $options === []) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.options must not be empty for enum features.",
            );
        }

        return $options;
    }

    /**
     * @param array<int, string> $options
     */
    private static function assertTypeSpecificRules(
        string $key,
        FeatureType $type,
        ?int $min,
        ?int $max,
        array $options,
        string $manifestPath,
    ): void {
        if ($type === FeatureType::Boolean && ($min !== null || $max !== null || $options !== [])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} bool features cannot define min, max or options.",
            );
        }

        if ($type === FeatureType::Enum && ($min !== null || $max !== null)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} enum features cannot define min or max.",
            );
        }

        if (($type === FeatureType::Integer || $type === FeatureType::String) && $options !== []) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} {$type->value} features cannot define options.",
            );
        }
    }
}
