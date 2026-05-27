<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleOriginKind;

final readonly class ModuleOrigin
{
    public function __construct(
        public ModuleOriginKind $kind,
        public string $installedVersion,
        public ?string $checksum = null,
    ) {
    }

    public static function forLocal(string $version): self
    {
        return new self(
            kind: ModuleOriginKind::Local,
            installedVersion: $version,
        );
    }

    public static function forZip(string $version, string $checksum): self
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
    public static function fromArray(array $data, string $contextPath): self
    {
        if (! isset($data['kind']) || ! \is_string($data['kind'])) {
            throw InvalidModuleStateException::forPath($contextPath, 'source.kind must be a non-empty string.');
        }

        $kind = ModuleOriginKind::tryFrom($data['kind']);

        if ($kind === null) {
            $allowed = implode(', ', array_column(ModuleOriginKind::cases(), 'value'));

            throw InvalidModuleStateException::forPath(
                $contextPath,
                "source.kind [{$data['kind']}] is not valid; allowed values: {$allowed}.",
            );
        }

        if (! isset($data['installed_version']) || ! \is_string($data['installed_version']) || trim($data['installed_version']) === '') {
            throw InvalidModuleStateException::forPath($contextPath, 'source.installed_version must be a non-empty string.');
        }

        $checksum = null;

        if (isset($data['checksum'])) {
            if (! \is_string($data['checksum'])) {
                throw InvalidModuleStateException::forPath($contextPath, 'source.checksum must be a string when present.');
            }
            $checksum = $data['checksum'];
        }

        return new self(
            kind: $kind,
            installedVersion: $data['installed_version'],
            checksum: $checksum,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [
            'installed_version' => $this->installedVersion,
            'kind' => $this->kind->value,
        ];

        if ($this->checksum !== null) {
            $data['checksum'] = $this->checksum;
        }

        ksort($data);

        return $data;
    }

    public function withInstalledVersion(string $version): self
    {
        return new self(
            kind: $this->kind,
            installedVersion: $version,
            checksum: $this->checksum,
        );
    }
}
