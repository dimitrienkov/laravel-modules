<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

final readonly class ModuleStateDocument
{
    public function __construct(
        public ModuleState $state,
        public FeatureValues $values,
        // `source` is the installed module's provenance (ModuleOrigin), matching
        // the state.json `source` key — not the staging ModuleSourceKind axis.
        public ?ModuleOrigin $source = null,
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

        if ($this->source instanceof ModuleOrigin) {
            $data['source'] = $this->source->toArray();
        }

        return $data;
    }
}
