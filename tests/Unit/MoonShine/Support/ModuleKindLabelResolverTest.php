<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Support;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleKindLabelResolver;
use Illuminate\Contracts\Translation\Translator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleKindLabelResolver::class)]
#[Group('moonshine')]
final class ModuleKindLabelResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function resolvesEveryKindToItsLangKey(): void
    {
        /** @var Translator&MockInterface $translator */
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')->once()
            ->with('module-loader::admin.kinds.module')->andReturn('Modules');
        $translator->shouldReceive('get')->once()
            ->with('module-loader::admin.kinds.subsystem')->andReturn('Subsystems');
        $translator->shouldReceive('get')->once()
            ->with('module-loader::admin.kinds.integration')->andReturn('Integrations');

        $resolver = new ModuleKindLabelResolver($translator);

        self::assertSame('Modules', $resolver->label(ModuleKind::Module));
        self::assertSame('Subsystems', $resolver->label(ModuleKind::Subsystem));
        self::assertSame('Integrations', $resolver->label(ModuleKind::Integration));
    }

    #[Test]
    public function fallsBackToEnumValueWhenTranslationIsNotAString(): void
    {
        /** @var Translator&MockInterface $translator */
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')->once()->andReturn(['unexpected' => 'array']);

        $resolver = new ModuleKindLabelResolver($translator);

        self::assertSame('module', $resolver->label(ModuleKind::Module));
    }
}
