<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Config\Repository;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use ReflectionClass;
use ReflectionException;

final readonly class MoonShineLoaderService
{
    public function __construct(
        private CoreContract $core,
        private Repository   $config,
    ) {
    }

    public function autoload(): void
    {
        $this->core->autoload();
        
        $pages = $this->discoverPages();
        $resources = $this->discoverResources();

        if (! empty($resources)) {
            $this->core->resources($resources);
        }

        if (! empty($pages)) {
            $this->core->pages($pages);
        }
    }

    /**
     * @return list<class-string<PageContract>>
     */
    private function discoverPages(): array
    {
        return $this->findInClassmap(PageContract::class);
    }

    /**
     * @return list<class-string<ResourceContract>>
     */
    private function discoverResources(): array
    {
        return $this->findInClassmap(ResourceContract::class);
    }

    /**
     * @template T
     * @param class-string<T> $interface
     * @return list<class-string<T>>
     */
    private function findInClassmap(string $interface): array
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = array_values($loaders)[0] ?? null;

        if (! $loader) {
            return [];
        }

        $classMap = $loader->getClassMap();
        $namespaces = $this->getModuleNamespaces();

        $found = [];

        foreach ($namespaces as $namespace) {
            foreach (array_keys($classMap) as $class) {
                if (! str_starts_with($class, $namespace)) {
                    continue;
                }

                if (! is_a($class, $interface, true)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($class);
                    if ($reflection->isAbstract()) {
                        continue;
                    }
                } catch (ReflectionException) {
                    continue;
                }

                $found[] = $class;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    private function getModuleNamespaces(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);
        if (! \is_array($directories)) {
            return [];
        }

        $namespaces = [];
        foreach ($directories as $dir) {
            if (! \is_string($dir)) {
                continue;
            }
            $namespaces[] = 'App\\' . basename($dir) . '\\';
        }

        return $namespaces;
    }
}
