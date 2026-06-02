<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Concerns;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Shared behaviour for the package's architectural generators (`make:use-case`,
 * `make:action`, `make:query`, `make:dto`, `make:vo`).
 *
 * Composes with {@see ModuleAwareGenerator}: with `--module` the class lands in
 * the module's architectural layer, without it in the host's `app/` equivalent.
 * The stub path is injected by the composition root (no `file_exists()` lookup, a
 * forbidden filesystem token in `src/`), the class suffix is enforced here, and
 * the host fallback adds the layer sub-namespace the bare `GeneratorCommand` lacks.
 *
 * Consuming commands must also `use ModuleAwareGenerator` and resolve the
 * `getDefaultNamespace` conflict in favour of this trait.
 */
trait ArchitecturalGenerator
{
    private string $stubsPath;

    public function __construct(Filesystem $files, string $stubsPath)
    {
        parent::__construct($files);

        $this->stubsPath = $stubsPath;
    }

    /**
     * The class-name suffix to enforce (e.g. `UseCase`), or `''` for none (VO).
     */
    abstract protected function classSuffix(): string;

    /**
     * The package stub filename, e.g. `use-case.stub`.
     */
    abstract protected function stubName(): string;

    protected function getStub(): string
    {
        return $this->stubsPath . '/' . $this->stubName();
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();
        $suffix = $this->classSuffix();

        if ($suffix === '' || Str::endsWith($name, $suffix)) {
            return $name;
        }

        return $name . $suffix;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $module = $this->module();

        return $module instanceof Module
            ? $this->moduleNamespace($module)
            : $rootNamespace . '\\' . $this->moduleSubNamespace();
    }
}
