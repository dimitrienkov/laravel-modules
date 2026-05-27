<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

final readonly class ModuleStateDocument
{
    public function __construct(
        public ModuleState $state,
        public FeatureValues $values,
        public ?ModuleOrigin $origin = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            ...$this->state->toArray(),
            'settings' => [
                'values' => $this->values->toArray(),
            ],
        ];

        if ($this->origin instanceof ModuleOrigin) {
            $data['source'] = $this->origin->toArray();
        }

        return $data;
    }
}
