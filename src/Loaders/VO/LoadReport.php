<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders\VO;

use InvalidArgumentException;

/**
 * Structured outcome of a single (loader × module) load attempt.
 *
 * Returned by every {@see \DimitrienkoV\LaravelModules\Contracts\LoaderInterface}
 * so the pipeline can centralise diagnostics: an applied report carries the
 * artifacts the loader contributed; a skipped report carries the reason a
 * precondition was missing. The value object is a pure data carrier — it holds
 * no module reference, feature values, or secrets, so {@see toArray()} is always
 * safe to log.
 *
 * Invariants (enforced in the constructor):
 * - Applied  ⇒ reason is null; artifacts MAY be empty (a no-op loader such as
 *   ServiceProviderLoader with no providers still "applied" — there was no
 *   missing precondition to report).
 * - Skipped  ⇒ reason is non-null and artifacts is empty. Skipped is reserved
 *   strictly for an absent precondition (directory / file / cache / console).
 *
 * Artifacts model (`array<string, list<string>>`): the key is the artifact
 * category (loader-defined, e.g. `config`, `web`, `migrations`); the value is a
 * list of basenames for loaders that enumerate files, or the single registered
 * relative path for path-registering loaders (Migration/Event/Command) that
 * hand a directory to Laravel without listing its contents.
 */
final readonly class LoadReport
{
    /**
     * @param array<string, list<string>> $artifacts
     */
    public function __construct(
        public LoadStatus $status,
        public array $artifacts = [],
        public ?SkipReason $reason = null,
    ) {
        if ($status === LoadStatus::Applied && $reason instanceof SkipReason) {
            throw new InvalidArgumentException('An applied LoadReport must not carry a skip reason.');
        }

        if ($status === LoadStatus::Skipped && ! $reason instanceof SkipReason) {
            throw new InvalidArgumentException('A skipped LoadReport must carry a skip reason.');
        }

        if ($status === LoadStatus::Skipped && $artifacts !== []) {
            throw new InvalidArgumentException('A skipped LoadReport must not carry artifacts.');
        }
    }

    /**
     * @param array<string, list<string>> $artifacts
     */
    public static function applied(array $artifacts = []): self
    {
        return new self(LoadStatus::Applied, $artifacts);
    }

    public static function skipped(SkipReason $reason): self
    {
        return new self(LoadStatus::Skipped, reason: $reason);
    }

    public function wasApplied(): bool
    {
        return $this->status === LoadStatus::Applied;
    }

    /**
     * Whitelisted scalar projection — safe to write to a log channel. Contains
     * only the status string, the optional skip reason value, and artifact
     * basenames/paths. Never a Module, feature values, or secrets.
     *
     * @return array{status: string, reason?: string, artifacts: array<string, list<string>>}
     */
    public function toArray(): array
    {
        $data = ['status' => $this->status->value];

        if ($this->reason instanceof SkipReason) {
            $data['reason'] = $this->reason->value;
        }

        $data['artifacts'] = $this->artifacts;

        return $data;
    }
}
