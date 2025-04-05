<?php

namespace DimitrienkoV\LaravelModules\Tests;

use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected LegacyMockInterface|MockInterface $mockConfigRepository;

    public function mockConfigData(): void
    {
        $config = require __DIR__ . '/../config/modules.php';

        $this->mockConfigRepository
            ->shouldReceive('get')
            ->andReturnUsing(
                static fn (string $arg) => collect(explode('.', $arg))
                ->slice(1)
                ->reduce(static fn ($result, string $key) => $result[$key] ?? null, $config)
            )->times(3);
    }
}
