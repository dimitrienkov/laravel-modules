<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListModulesUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function returnsAllModulesWithoutFilter(): void
    {
        $modules = [$this->makeModule('blog', true), $this->makeModule('users', false)];
        $useCase = $this->makeUseCase($modules);

        $result = $useCase->execute();

        $this->assertCount(2, $result->modules);
    }

    #[Test]
    public function filtersEnabledModules(): void
    {
        $modules = [$this->makeModule('blog', true), $this->makeModule('users', false)];
        $useCase = $this->makeUseCase($modules);

        $result = $useCase->execute(enabledFilter: true);

        $this->assertCount(1, $result->modules);
        $this->assertSame('blog', $result->modules[0]->name);
    }

    #[Test]
    public function filtersDisabledModules(): void
    {
        $modules = [$this->makeModule('blog', true), $this->makeModule('users', false)];
        $useCase = $this->makeUseCase($modules);

        $result = $useCase->execute(enabledFilter: false);

        $this->assertCount(1, $result->modules);
        $this->assertSame('users', $result->modules[0]->name);
    }

    #[Test]
    public function returnsEmptyListWhenNoModules(): void
    {
        $useCase = $this->makeUseCase([]);

        $result = $useCase->execute();

        $this->assertSame([], $result->modules);
    }

    /**
     * @param list<Module> $modules
     */
    private function makeUseCase(array $modules): ListModulesUseCase
    {
        /** @var ModuleRegistryInterface&Mockery\MockInterface $registry */
        $registry = Mockery::mock(ModuleRegistryInterface::class);
        $registry->shouldReceive('all')->andReturn($modules);

        return new ListModulesUseCase($registry);
    }

    private function makeModule(string $name, bool $enabled): Module
    {
        return new Module(
            name: $name,
            displayName: ucfirst($name),
            namespace: 'App\\Modules\\' . ucfirst($name),
            path: '/app/Modules/' . ucfirst($name),
            meta: new ManifestMeta(
                name: $name,
                displayName: ucfirst($name),
                version: '1.0.0',
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
            ),
            state: new ModuleState(enabled: $enabled, installedAt: '2026-01-01T00:00:00+00:00'),
            features: new FeatureSchema([]),
        );
    }
}
