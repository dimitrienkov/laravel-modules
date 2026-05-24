<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureDefinitionFactory;

final readonly class FeatureSchema
{
    /**
     * @param array<string, FeatureDefinition> $definitions
     */
    public function __construct(
        private array $definitions,
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     */
    public static function fromArray(array $schema, string $manifestPath): self
    {
        $definitions = [];

        foreach ($schema as $key => $definition) {
            if (! \is_string($key)) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    'settings.schema keys must be feature names.'
                );
            }

            if (! \is_array($definition)) {
                throw InvalidManifestException::forPath(
                    $manifestPath,
                    "settings.schema.{$key} must be an object."
                );
            }

            /** @var array<string, mixed> $definition */
            $definitions[$key] = FeatureDefinitionFactory::fromArray($key, $definition, $manifestPath);
        }

        ksort($definitions);

        return new self($definitions);
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function definition(string $moduleName, string $key): FeatureDefinition
    {
        return $this->definitions[$key] ?? throw FeatureNotFoundException::forKey($moduleName, $key);
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->definitions as $key => $definition) {
            if ($definition->hasDefault) {
                /** @var bool|int|string $default */
                $default = $definition->default;
                $defaults[$key] = $default;
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [];

        foreach ($this->definitions as $key => $definition) {
            $schema[$key] = $definition->toArray();
        }

        return $schema;
    }
}
