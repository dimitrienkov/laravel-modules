<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\Enums;

/**
 * Fixed provenance of an installed module, persisted in state.json `source.kind`.
 *
 * Distinct axis from ModuleSourceKind (the format of the staging input):
 * provenance records where the installed module came from, and future sources
 * (e.g. git/registry) would extend this enum in parallel with ModuleSourceKind.
 */
enum ModuleOriginKind: string
{
    case Local = 'local';
    case Zip = 'zip';

    /**
     * Whether this provenance must carry an artifact checksum.
     *
     * Exhaustive match: a future kind without an arm is caught by PHPStan,
     * forcing a deliberate decision about checksum requirements.
     */
    public function requiresChecksum(): bool
    {
        return match ($this) {
            self::Zip => true,
            self::Local => false,
        };
    }
}
