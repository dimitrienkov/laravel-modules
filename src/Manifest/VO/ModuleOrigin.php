<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleOriginKind;
use InvalidArgumentException;

final readonly class ModuleOrigin
{
    public function __construct(
        public ModuleOriginKind $kind,
        public Version $installedVersion,
        public ?Checksum $checksum = null,
    ) {
        if ($kind->requiresChecksum() && ! $checksum instanceof Checksum) {
            throw new InvalidArgumentException("Module origin [{$kind->value}] requires a checksum.");
        }

        if (! $kind->requiresChecksum() && $checksum instanceof Checksum) {
            throw new InvalidArgumentException("Module origin [{$kind->value}] must not carry a checksum.");
        }
    }

    public static function forLocal(Version $version): self
    {
        return new self(
            kind: ModuleOriginKind::Local,
            installedVersion: $version,
        );
    }

    public static function forZip(Version $version, Checksum $checksum): self
    {
        return new self(
            kind: ModuleOriginKind::Zip,
            installedVersion: $version,
            checksum: $checksum,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $statePath): self
    {
        $unknownKeys = array_diff(array_keys($data), ['kind', 'installed_version', 'checksum']);

        if ($unknownKeys !== []) {
            throw InvalidModuleStateException::forPath(
                $statePath,
                'source contains unknown keys: ' . implode(', ', $unknownKeys) . '.',
            );
        }

        if (! isset($data['kind']) || ! \is_string($data['kind'])) {
            throw InvalidModuleStateException::forPath($statePath, 'source.kind must be a non-empty string.');
        }

        $kind = ModuleOriginKind::tryFrom($data['kind']);

        if ($kind === null) {
            $allowed = implode(', ', array_column(ModuleOriginKind::cases(), 'value'));

            throw InvalidModuleStateException::forPath(
                $statePath,
                "source.kind [{$data['kind']}] is not valid; allowed values: {$allowed}.",
            );
        }

        $version = $data['installed_version'] ?? null;

        if (! \is_string($version) || trim($version) === '') {
            throw InvalidModuleStateException::forPath($statePath, 'source.installed_version must be a non-empty string.');
        }

        try {
            // The kind <-> checksum invariant lives in the constructor and the
            // semver grammar lives in Version (single owners); fromArray only
            // parses the raw values and re-throws those domain errors with
            // state-path context attached.
            return new self(
                kind: $kind,
                installedVersion: new Version($version),
                checksum: self::parseChecksum($data, $statePath),
            );
        } catch (InvalidArgumentException $e) {
            throw InvalidModuleStateException::forPath($statePath, $e->getMessage(), $e);
        }
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind->value,
            'installed_version' => $this->installedVersion->value,
        ];

        if ($this->checksum instanceof Checksum) {
            $data['checksum'] = $this->checksum->value;
        }

        return $data;
    }

    /**
     * Parse only: read the raw `checksum` key without enforcing the per-kind
     * presence/absence invariant — that belongs to the constructor. An absent
     * key is null (array_key_exists, not isset, so an explicit null stays a
     * present-but-invalid non-string value rather than passing as absent).
     *
     * @param array<string, mixed> $data
     */
    private static function parseChecksum(array $data, string $statePath): ?Checksum
    {
        if (! \array_key_exists('checksum', $data)) {
            return null;
        }

        if (! \is_string($data['checksum'])) {
            throw InvalidModuleStateException::forPath($statePath, 'source.checksum must be a string when present.');
        }

        try {
            return new Checksum($data['checksum']);
        } catch (InvalidArgumentException $e) {
            throw InvalidModuleStateException::forPath($statePath, $e->getMessage(), $e);
        }
    }
}
