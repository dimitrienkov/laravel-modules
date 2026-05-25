<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\Translator;

final readonly class LangLoader implements LoaderInterface
{
    public function __construct(
        private Translator $translator,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $langDir = $this->layout->langDir($module);

        if (! $this->filesystem->isDirectory($langDir)) {
            return;
        }

        $this->translator->addNamespace($module->name, $langDir);
    }

    public function priority(): int
    {
        return 32;
    }
}
