<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class FeatureDefinition
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

    private const array SUPPORTED_TYPES = [
        'bool' => true,
        'int' => true,
        'string' => true,
        'enum' => true,
    ];

    /**
     * @param array<int, string> $options
     */
    public function __construct(
        public string $key,
        public string $type,
        public bool $hasDefault,
        public bool|int|string|null $default,
        public ?int $min,
        public ?int $max,
        public array $options,
        public ?string $label = null,
        public ?string $description = null,
        public ?string $group = null,
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     */
    public static function fromArray(string $key, array $definition, string $manifestPath): self
    {
        if (trim($key) === '') {
            throw InvalidManifestException::forPath($manifestPath, 'settings.schema keys must be non-empty strings.');
        }

        self::assertKnownKeys($key, $definition, $manifestPath);

        $type = $definition['type'] ?? null;
        if (! \is_string($type) || ! isset(self::SUPPORTED_TYPES[$type])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.type must be one of: bool, int, string, enum."
            );
        }

        $min = self::optionalInteger($key, $definition, 'min', $manifestPath);
        $max = self::optionalInteger($key, $definition, 'max', $manifestPath);
        if ($min !== null && $max !== null && $min > $max) {
            throw InvalidManifestException::forPath($manifestPath, "settings.schema.{$key}.min cannot exceed max.");
        }

        $options = self::options($key, $type, $definition, $manifestPath);
        self::assertTypeSpecificRules($key, $type, $min, $max, $options, $manifestPath);

        $hasDefault = \array_key_exists('default', $definition);
        $default = $hasDefault
            ? self::normalizeValue($key, $type, $definition['default'], $min, $max, $options, $manifestPath, 'default')
            : null;

        $label = self::optionalStringField($key, $definition, 'label', $manifestPath);
        $description = self::optionalStringField($key, $definition, 'description', $manifestPath);
        $group = self::optionalStringField($key, $definition, 'group', $manifestPath);

        return new self(
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

    public function normalize(mixed $value, string $manifestPath): bool|int|string
    {
        return self::normalizeValue(
            key: $this->key,
            type: $this->type,
            value: $value,
            min: $this->min,
            max: $this->max,
            options: $this->options,
            manifestPath: $manifestPath,
            valueName: 'value',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = [
            'type' => $this->type,
        ];

        if ($this->hasDefault) {
            $definition['default'] = $this->default;
        }

        if ($this->min !== null) {
            $definition['min'] = $this->min;
        }

        if ($this->max !== null) {
            $definition['max'] = $this->max;
        }

        if ($this->options !== []) {
            $definition['options'] = $this->options;
        }

        if ($this->label !== null) {
            $definition['label'] = $this->label;
        }

        if ($this->description !== null) {
            $definition['description'] = $this->description;
        }

        if ($this->group !== null) {
            $definition['group'] = $this->group;
        }

        return $definition;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function assertKnownKeys(string $key, array $definition, string $manifestPath): void
    {
        foreach (array_keys($definition) as $definitionKey) {
            if (! isset(self::ALLOWED_KEYS[$definitionKey])) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key} contains unknown key [{$definitionKey}]."
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function optionalInteger(
        string $key,
        array $definition,
        string $field,
        string $manifestPath,
    ): ?int {
        if (! \array_key_exists($field, $definition)) {
            return null;
        }

        if (! \is_int($definition[$field])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$field} must be an integer."
            );
        }

        return $definition[$field];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<int, string>
     */
    private static function options(string $key, string $type, array $definition, string $manifestPath): array
    {
        if (! \array_key_exists('options', $definition)) {
            if ($type === 'enum') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key}.options must be a non-empty string list for enum features."
                );
            }

            return [];
        }

        if (! \is_array($definition['options']) || ! array_is_list($definition['options'])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.options must be a list of strings."
            );
        }

        $options = [];
        foreach ($definition['options'] as $option) {
            if (! \is_string($option) || $option === '') {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key}.options entries must be non-empty strings."
                );
            }

            $options[] = $option;
        }

        if ($type === 'enum' && $options === []) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.options must not be empty for enum features."
            );
        }

        return array_values(array_unique($options));
    }

    /**
     * @param array<int, string> $options
     */
    private static function assertTypeSpecificRules(
        string $key,
        string $type,
        ?int $min,
        ?int $max,
        array $options,
        string $manifestPath,
    ): void {
        if ($type === 'bool' && ($min !== null || $max !== null || $options !== [])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} bool features cannot define min, max or options."
            );
        }

        if ($type === 'enum' && ($min !== null || $max !== null)) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} enum features cannot define min or max."
            );
        }

        if (($type === 'int' || $type === 'string') && $options !== []) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key} {$type} features cannot define options."
            );
        }
    }

    /**
     * @param array<int, string> $options
     */
    private static function normalizeValue(
        string $key,
        string $type,
        mixed $value,
        ?int $min,
        ?int $max,
        array $options,
        string $manifestPath,
        string $valueName,
    ): bool|int|string {
        return match ($type) {
            'bool' => self::normalizeBool($key, $value, $manifestPath, $valueName),
            'int' => self::normalizeInt($key, $value, $min, $max, $manifestPath, $valueName),
            'string' => self::normalizeString($key, $value, $min, $max, $manifestPath, $valueName),
            'enum' => self::normalizeEnum($key, $value, $options, $manifestPath, $valueName),
            default => throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.type [{$type}] is not supported."
            ),
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
                "settings.schema.{$key}.{$valueName} must be a boolean."
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
                "settings.schema.{$key}.{$valueName} must be an integer."
            );
        }

        if ($min !== null && $value < $min) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be greater than or equal to {$min}."
            );
        }

        if ($max !== null && $value > $max) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} must be less than or equal to {$max}."
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
                "settings.schema.{$key}.{$valueName} must be a string."
            );
        }

        $length = \strlen($value);
        if ($min !== null && $length < $min) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} length must be greater than or equal to {$min}."
            );
        }

        if ($max !== null && $length > $max) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$valueName} length must be less than or equal to {$max}."
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function optionalStringField(
        string $key,
        array $definition,
        string $field,
        string $manifestPath,
    ): ?string {
        if (! \array_key_exists($field, $definition)) {
            return null;
        }

        if (! \is_string($definition[$field])) {
            throw InvalidManifestException::forPath(
                $manifestPath,
                "settings.schema.{$key}.{$field} must be a string."
            );
        }

        return $definition[$field];
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
                "settings.schema.{$key}.{$valueName} must be one of: " . implode(', ', $options) . '.'
            );
        }

        return $value;
    }
}
