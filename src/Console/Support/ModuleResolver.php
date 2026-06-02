<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Support\Str;

/**
 * The single place make:* commands normalise and resolve a `--module` option.
 *
 * Both the {@see \DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator}
 * trait and the standalone {@see \DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration}
 * (an anonymous-migration command the trait cannot wrap) delegate here, so the
 * `Str::snake(trim(...))` normalisation and the registry lookup live once.
 *
 * Resolution does an explicit existence check before the lookup rather than a
 * find()-and-catch, so an unknown module surfaces as a {@see ModuleNotFoundException}
 * without using exceptions for control flow. Module access stays behind the
 * {@see ModuleRegistryInterface} contract only.
 */
final readonly class ModuleResolver
{
    public function __construct(private ModuleRegistryInterface $registry)
    {
    }

    /**
     * Normalise a raw `--module` option to its canonical (snake_case) module
     * name, or `null` when the option is absent or blank (host mode).
     */
    public function normalize(mixed $option): ?string
    {
        if (! \is_string($option)) {
            return null;
        }

        $trimmed = trim($option);

        return $trimmed === '' ? null : Str::snake($trimmed);
    }

    /**
     * Resolve a raw `--module` option to its module, or `null` for host mode.
     *
     * @throws ModuleNotFoundException when a non-blank name names no known module
     */
    public function resolve(mixed $option): ?Module
    {
        $name = $this->normalize($option);

        if ($name === null) {
            return null;
        }

        if (! $this->registry->has($name)) {
            throw ModuleNotFoundException::forName($name);
        }

        return $this->registry->find($name);
    }
}
