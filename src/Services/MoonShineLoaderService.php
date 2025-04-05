<?php

namespace DimitrienkoV\LaravelModules\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\Core;

final readonly class MoonShineLoaderService
{
    private const string MODULES_NAMESPACE = 'App\\Modules\\';
    private const string MOONSHINE_DIR = 'MoonShine';
    private const string CACHE_KEY = 'moonshine:autoload';
    private const string DISCOVERY_PATTERN = '/^%s([^\\\\]+)\\\\%s\\\\(Resources|Pages)\\\\/i';

    /**
     * @param Repository $cache
     * @param CoreContract $core
     */
    public function __construct(
        private Repository   $cache,
        private CoreContract $core
    ) {
    }

    public function autoload(): void
    {
        $this->core->autoload()
            ->resources($this->getResources())
            ->pages($this->getPages());
    }

    /**
     * @return array<class-string<PageContract>>
     */
    public function getPages(): array
    {
        return $this->filterClasses(PageContract::class);
    }

    /**
     * @return array<class-string<ResourceContract>>
     */
    public function getResources(): array
    {
        return $this->filterClasses(ResourceContract::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $contract
     * @return array<class-string<T>>
     */
    private function filterClasses(string $contract): array
    {
        return $this->getMoonShineClasses()
            ->filter(
                static fn (string $class): bool => class_exists($class) && is_subclass_of($class, $contract)
            )
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function getMoonShineClasses(): Collection
    {
        return $this->cache->rememberForever(
            self::CACHE_KEY,
            fn (): Collection => $this->discoverClasses()
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function discoverClasses(): Collection
    {
        return Collection::make(ClassLoader::getRegisteredLoaders())
            ->flatMap(static fn (ClassLoader $loader): array => $loader->getClassMap())
            ->keys()
            ->filter($this->createMoonShineComponentFilter())
            ->unique()
            ->values();
    }

    /**
     * @return callable(string): bool
     */
    private function createMoonShineComponentFilter(): callable
    {
        $pattern = \sprintf(
            self::DISCOVERY_PATTERN,
            preg_quote(self::MODULES_NAMESPACE, '/'),
            preg_quote(self::MOONSHINE_DIR, '/')
        );

        return static fn (string $class): bool => preg_match($pattern, $class) === 1;
    }
}
