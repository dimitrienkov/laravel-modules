<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListModulesUseCase::class)]
#[Group('lifecycle')]
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

    #[Test]
    public function filtersByGroup(): void
    {
        $modules = [
            $this->makeModule('blog', true, group: 'content'),
            $this->makeModule('users', true),
        ];
        $useCase = $this->makeUseCase($modules);

        $result = $useCase->execute(groupFilter: 'content');

        $this->assertCount(1, $result->modules);
        $this->assertSame('blog', $result->modules[0]->name);
    }

    #[Test]
    public function returnsEmptyListForGroupWithoutMatches(): void
    {
        $modules = [
            $this->makeModule('blog', true, group: 'content'),
            $this->makeModule('users', true),
        ];
        $useCase = $this->makeUseCase($modules);

        $result = $useCase->execute(groupFilter: 'missing');

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

    private function makeModule(string $name, bool $enabled, ModuleKind $kind = ModuleKind::Module, ?string $group = null): Module
    {
        return new Module(
            name: $name,
            displayName: ucfirst($name),
            namespace: 'App\\Modules\\' . ucfirst($name),
            path: '/app/Modules/' . ucfirst($name),
            schemaVersion: ManifestValidator::CURRENT_SCHEMA_VERSION,
            meta: new ManifestMeta(
                name: $name,
                displayName: ucfirst($name),
                kind: $kind,
                version: '1.0.0',
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
                group: $group,
            ),
            state: new ModuleState(enabled: $enabled, installedAt: '2026-01-01T00:00:00+00:00'),
            features: new FeatureSchema([]),
        );
    }
}
