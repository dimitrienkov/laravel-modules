<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class FeatureValues
{
    /**
     * @param array<string, bool|int|string> $values
     */
    public function __construct(
        private FeatureSchema $schema,
        private array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(
        array $values,
        FeatureSchema $schema,
        string $moduleName,
        string $manifestPath,
    ): self {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (! \is_string($key)) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    'settings.values keys must be feature names.'
                );
            }

            if (! $schema->has($key)) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.values.{$key} is not defined in settings.schema for module [{$moduleName}]."
                );
            }

            $normalized[$key] = $schema->definition($moduleName, $key)->normalize($value, $manifestPath);
        }

        ksort($normalized);

        return new self($schema, $normalized);
    }

    public function get(string $moduleName, string $key): bool|int|string
    {
        if (\array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        $definition = $this->schema->definition($moduleName, $key);
        if ($definition->hasDefault) {
            /** @var bool|int|string $default */
            $default = $definition->default;

            return $default;
        }

        throw FeatureNotFoundException::forKey($moduleName, $key);
    }

    public function with(string $moduleName, string $key, mixed $value, string $manifestPath): self
    {
        $definition = $this->schema->definition($moduleName, $key);
        $values = $this->values;
        $values[$key] = $definition->normalize($value, $manifestPath);

        ksort($values);

        return new self($this->schema, $values);
    }

    /**
     * Explicit values only. Defaults stay in FeatureSchema and are not persisted here.
     *
     * @return array<string, bool|int|string>
     */
    public function explicitValues(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return $this->explicitValues();
    }
}
