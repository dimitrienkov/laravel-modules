<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\PolicyLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyLoader::class)]
#[Group('loaders')]
final class PolicyLoaderTest extends TestCase
{
    use UsesTempDirectory;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('policy-loader');
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersPolicyForMatchingModel(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($policiesDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Post.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Post {}',
        );
        file_put_contents(
            $policiesDir . '/PostPolicy.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Policies; class PostPolicy { public function view($user, $post): bool { return true; } }',
        );
        $this->registerAutoloader($modelsDir . '/Post.php', 'App\\Modules\\Blog\\Domain\\Models\\Post');
        $this->registerAutoloader($policiesDir . '/PostPolicy.php', 'App\\Modules\\Blog\\Domain\\Policies\\PostPolicy');
        $gate = new Gate(new Container(), static fn() => null);

        $report = (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $policies = $gate->policies();
        self::assertArrayHasKey('App\\Modules\\Blog\\Domain\\Models\\Post', $policies);
        self::assertSame('App\\Modules\\Blog\\Domain\\Policies\\PostPolicy', $policies['App\\Modules\\Blog\\Domain\\Models\\Post']);
        self::assertTrue($report->wasApplied());
        self::assertSame(['policies' => ['PostPolicy.php']], $report->artifacts);
    }

    #[Test]
    public function skipsWhenModelClassDoesNotExist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        mkdir($policiesDir, 0755, true);
        file_put_contents(
            $policiesDir . '/CommentPolicy.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Policies; class CommentPolicy {}',
        );
        $this->registerAutoloader($policiesDir . '/CommentPolicy.php', 'App\\Modules\\Blog\\Domain\\Policies\\CommentPolicy');
        $gate = new Gate(new Container(), static fn() => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $gate->policies());
    }

    #[Test]
    public function skipsWhenPolicyClassDoesNotExist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $policiesDir = $modulePath . '/Domain/Policies';
        $modelsDir = $modulePath . '/Domain/Models';
        mkdir($policiesDir, 0755, true);
        mkdir($modelsDir, 0755, true);
        file_put_contents(
            $modelsDir . '/Tag.php',
            '<?php namespace App\\Modules\\Blog\\Domain\\Models; class Tag {}',
        );
        $this->registerAutoloader($modelsDir . '/Tag.php', 'App\\Modules\\Blog\\Domain\\Models\\Tag');
        file_put_contents($policiesDir . '/TagPolicy.php', '<?php // no class defined');
        $gate = new Gate(new Container(), static fn() => null);

        (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $gate->policies());
    }

    #[Test]
    public function returnsEarlyWhenPoliciesDirectoryIsMissing(): void
    {
        $gate = new Gate(new Container(), static fn() => null);

        $report = (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertSame([], $gate->policies());
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
    }

    #[Test]
    public function skipsWithEmptyDirectoryReasonWhenNoPolicyFilesPresent(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Domain/Policies', 0755, true);
        $gate = new Gate(new Container(), static fn() => null);

        $report = (new PolicyLoader($gate, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::EmptyDirectory, $report->reason);
    }

    private function registerAutoloader(string $file, string $class): void
    {
        $autoloader = static function (string $requested) use ($file, $class): void {
            if ($requested === $class) {
                require_once $file;
            }
        };

        spl_autoload_register($autoloader);
        $this->autoloaders[] = $autoloader;
    }
}
