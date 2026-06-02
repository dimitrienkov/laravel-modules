<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Support;

use Symfony\Component\Console\Input\InputOption;

/**
 * Factory for the shared `--module` console option.
 *
 * Both the {@see \DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator}
 * trait and the standalone {@see \DimitrienkoV\LaravelModules\Console\Commands\Make\MakeMigration}
 * expose `--module`; defining its name and value mode here keeps the two from
 * drifting. Only the human-readable description varies per command, so it is the
 * single parameter. This is intentionally separate from {@see ModuleResolver},
 * which owns normalising and resolving the option's *value*, not declaring it.
 */
final class ModuleOption
{
    public static function make(string $description): InputOption
    {
        return new InputOption('module', null, InputOption::VALUE_REQUIRED, $description);
    }
}
