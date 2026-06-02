<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support\Stubs;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class RecordingConsoleKernel extends ConsoleKernel
{
    /** @var list<string> */
    public array $addedRoutePaths = [];

    public function __construct() {}

    /**
     * @param list<string> $paths
     */
    public function addCommandRoutePaths(array $paths): static
    {
        $this->addedRoutePaths = [...$this->addedRoutePaths, ...$paths];

        return $this;
    }
}
