<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

final readonly class ModuleStateDocument
{
    public function __construct(
        public ModuleState $state,
        public FeatureValues $values,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->state->toArray(),
            'settings' => [
                'values' => $this->values->toArray(),
            ],
        ];
    }
}
