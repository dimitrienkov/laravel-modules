<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest\VO;

use InvalidArgumentException;

/**
 * Validated content digest of a source archive.
 *
 * Single-value VO holding a bare 64-character lowercase sha256 hex string.
 * The algorithm is a concept constant ({@see self::ALGORITHM}), not stored
 * state: state.json persists only the bare hex, and the algorithm is always
 * the same. Hashing happens in infrastructure; this VO accepts a ready digest
 * (no I/O inside the VO).
 */
final readonly class Checksum
{
    public const string ALGORITHM = 'sha256';

    private const string PATTERN = '/^[0-9a-f]{64}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $this->value) !== 1) {
            throw new InvalidArgumentException(
                "Checksum must be a 64-character lowercase hex sha256 digest, got [{$this->value}].",
            );
        }
    }
}
