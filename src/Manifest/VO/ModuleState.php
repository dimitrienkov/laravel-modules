<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\Parsing\ManifestFieldReader;

final readonly class ModuleState
{
    private const array ALLOWED_KEYS = [
        'enabled' => true,
        'installed_at' => true,
        'updated_at' => true,
    ];

    public function __construct(
        public bool $enabled,
        public ?string $installedAt,
        public ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function fromArray(array $state, string $contextPath): self
    {
        ManifestFieldReader::assertAllowedKeys($state, self::ALLOWED_KEYS, 'state', $contextPath);

        $enabled = ManifestFieldReader::requiredBool($state, 'enabled', 'state', $contextPath);
        $installedAt = ManifestFieldReader::optionalString($state, 'installed_at', 'state', $contextPath);
        $updatedAt = ManifestFieldReader::optionalString($state, 'updated_at', 'state', $contextPath);

        return new self($enabled, $installedAt, $updatedAt);
    }

    public static function disabledDefault(): self
    {
        return new self(enabled: false, installedAt: null, updatedAt: null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $state = [
            'enabled' => $this->enabled,
        ];

        if ($this->installedAt !== null) {
            $state['installed_at'] = $this->installedAt;
        }

        if ($this->updatedAt !== null) {
            $state['updated_at'] = $this->updatedAt;
        }

        return $state;
    }
}
