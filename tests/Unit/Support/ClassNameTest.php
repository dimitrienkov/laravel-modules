<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Support\ClassName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassName::class)]
#[Group('unit')]
final class ClassNameTest extends TestCase
{
    #[Test]
    public function returnsTheSegmentAfterTheFinalNamespaceSeparator(): void
    {
        self::assertSame(
            'BlogServiceProvider',
            ClassName::short('App\\Modules\\Blog\\Providers\\BlogServiceProvider'),
        );
    }

    #[Test]
    public function returnsTheWholeStringForAClassWithoutANamespace(): void
    {
        self::assertSame('Helper', ClassName::short('Helper'));
    }

    #[Test]
    public function handlesASingleLevelNamespace(): void
    {
        self::assertSame('Factory', ClassName::short('App\\Factory'));
    }
}
