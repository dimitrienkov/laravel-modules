<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\Translator;

final readonly class LangLoader implements LoaderInterface
{
    public function __construct(
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $langDir = $this->layout->langDir($module);

        if (! $this->filesystem->isDirectory($langDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $this->hooks->callAfterResolving(
            'translator',
            static function (Translator $translator) use ($module, $langDir): void {
                $translator->addNamespace($module->name, $langDir);
            },
        );

        return LoadReport::applied(['lang' => [$this->layout->relativeToModule($module, $langDir)]]);
    }

    public function priority(): int
    {
        return 32;
    }
}
