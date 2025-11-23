<?php

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\OptimizerCollectionContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;

final readonly class MoonShineLoaderService
{
    public function __construct(
        private CoreContract                 $core,
        private OptimizerCollectionContract $optimizer,
        private Repository                  $config,
    ) {
    }

    public function autoload(): void
    {
        $namespaces = $this->getModuleNamespaces();

        /** @var list<class-string<PageContract>> $pages */
        $pages = $this->discoverPages($namespaces);

        /** @var list<class-string<ResourceContract>> $resources */
        $resources = $this->discoverResources($namespaces);

        $this->core
            ->pages($pages)
            ->resources($resources);
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

    /**
     * @param list<string> $namespaces
     * @return list<class-string<PageContract>>
     */
    private function discoverPages(array $namespaces): array
    {
        $allPagesArrays = array_map(
            fn (string $ns): array => $this->optimizer->getType(PageContract::class, $ns),
            $namespaces
        );

        return array_values(array_merge([], ...$allPagesArrays));
    }

    /**
     * @param list<string> $namespaces
     * @return list<class-string<ResourceContract>>
     */
    private function discoverResources(array $namespaces): array
    {
        $allResourcesArrays = array_map(
            fn (string $ns): array => $this->optimizer->getType(ResourceContract::class, $ns),
            $namespaces
        );

        return array_values(array_merge([], ...$allResourcesArrays));
    }
}
