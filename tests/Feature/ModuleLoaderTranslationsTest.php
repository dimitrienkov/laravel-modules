<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleKindLabelResolver;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use Illuminate\Contracts\Translation\Translator;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ModuleLoaderTranslationsTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ModuleLoaderServiceProvider::class];
    }

    #[Test]
    public function registersTheModuleLoaderTranslationNamespace(): void
    {
        $this->app->setLocale('en');
        $translator = $this->app->make(Translator::class);

        self::assertSame('Modules', $translator->get('module-loader::admin.title'));
        self::assertSame('Subsystems', $translator->get('module-loader::admin.kinds.subsystem'));
        self::assertSame('Settings', $translator->get('module-loader::admin.actions.settings'));
    }

    #[Test]
    public function kindResolverReturnsLocalizedLabels(): void
    {
        $resolver = $this->app->make(ModuleKindLabelResolver::class);

        $this->app->setLocale('en');
        self::assertSame('Integrations', $resolver->label(ModuleKind::Integration));

        $this->app->setLocale('ru');
        self::assertSame('Интеграции', $resolver->label(ModuleKind::Integration));
        self::assertSame('Модули', $resolver->label(ModuleKind::Module));
    }
}
