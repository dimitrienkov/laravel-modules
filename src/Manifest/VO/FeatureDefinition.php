<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\Enums\FeatureType;
use DimitrienkoV\LaravelModules\Manifest\Parsing\FeatureValueNormalizer;

final readonly class FeatureDefinition
{
    /**
     * @param array<int, string> $options
     */
    public function __construct(
        public string $key,
        public FeatureType $type,
        public bool $hasDefault,
        public bool|int|string|null $default,
        public ?int $min,
        public ?int $max,
        public array $options,
        public ?string $label = null,
        public ?string $description = null,
        public ?string $group = null,
    ) {}

    public function normalize(mixed $value, string $manifestPath): bool|int|string
    {
        return FeatureValueNormalizer::normalize(
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
            'type' => $this->type->value,
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
}
