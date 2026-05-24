<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Architecture;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

final class LoadersTest extends TestCase
{
    #[Test]
    public function every_loader_is_final_and_implements_loader_interface(): void
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__ . '/../../src/Loaders')
            ->depth(0)
            ->name('*.php');

        foreach ($files as $file) {
            $class = 'DimitrienkoV\\LaravelModules\\Loaders\\' . $file->getBasename('.php');

            self::assertTrue(class_exists($class), "Loader class [{$class}] does not exist.");
            self::assertTrue(is_subclass_of($class, LoaderInterface::class), "Loader [{$class}] must implement LoaderInterface.");

            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal(), "Loader [{$class}] must be final.");
        }
    }
}
