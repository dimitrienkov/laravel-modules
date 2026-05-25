<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support\Stubs;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class CommandRecordingKernel extends ConsoleKernel
{
    /** @var list<string> */
    public array $addedCommandPaths = [];

    public function __construct()
    {
    }

    /**
     * @param list<string> $paths
     */
    public function addCommandPaths(array $paths): static
    {
        $this->addedCommandPaths = [...$this->addedCommandPaths, ...$paths];

        return $this;
    }
}
