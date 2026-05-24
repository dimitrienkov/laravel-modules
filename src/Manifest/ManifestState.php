<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;

final readonly class ManifestState
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
    public static function fromArray(array $state, string $manifestPath): self
    {
        foreach (array_keys($state) as $key) {
            if (! isset(self::ALLOWED_KEYS[$key])) {
                throw InvalidManifestException::forPath($manifestPath, "state contains unknown key [{$key}].");
            }
        }

        if (! \array_key_exists('enabled', $state) || ! \is_bool($state['enabled'])) {
            throw InvalidManifestException::forPath($manifestPath, 'state.enabled must be a boolean.');
        }

        $installedAt = $state['installed_at'] ?? null;
        if ($installedAt !== null && ! \is_string($installedAt)) {
            throw InvalidManifestException::forPath($manifestPath, 'state.installed_at must be a string or null.');
        }

        $updatedAt = $state['updated_at'] ?? null;
        if ($updatedAt !== null && ! \is_string($updatedAt)) {
            throw InvalidManifestException::forPath($manifestPath, 'state.updated_at must be a string or null.');
        }

        return new self($state['enabled'], $installedAt, $updatedAt);
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
