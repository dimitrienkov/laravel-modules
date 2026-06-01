<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleGroupLabelResolver::class)]
#[Group('lifecycle')]
final class ModuleGroupLabelResolverTest extends TestCase
{
    #[Test]
    public function rendersLabelWithCodeWhenMappingExists(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('Content Management (content)', $resolver->label('content'));
    }

    #[Test]
    public function fallsBackToCodeWhenMappingMissing(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('billing', $resolver->label('billing'));
    }

    #[Test]
    public function returnsEmptyStringForNullGroup(): void
    {
        $resolver = $this->makeResolver(['content' => 'Content Management']);

        self::assertSame('', $resolver->label(null));
    }

    #[Test]
    public function fallsBackToCodeWhenGroupsConfigIsNotArray(): void
    {
        $resolver = $this->makeResolver('not-an-array');

        self::assertSame('content', $resolver->label('content'));
    }

    #[Test]
    public function fallsBackToCodeWhenLabelIsNotString(): void
    {
        $resolver = $this->makeResolver(['content' => ['nested' => 'value']]);

        self::assertSame('content', $resolver->label('content'));
    }

    #[Test]
    public function fallsBackToCodeWhenLabelIsBlank(): void
    {
        $resolver = $this->makeResolver(['content' => '   ']);

        self::assertSame('content', $resolver->label('content'));
    }

    private function makeResolver(mixed $groups): ModuleGroupLabelResolver
    {
        return new ModuleGroupLabelResolver(new Repository(['modules' => ['groups' => $groups]]));
    }
}
