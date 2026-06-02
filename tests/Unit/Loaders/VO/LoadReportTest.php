<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders\VO;

use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadReport::class)]
#[CoversClass(LoadStatus::class)]
#[CoversClass(SkipReason::class)]
#[Group('unit')]
final class LoadReportTest extends TestCase
{
    #[Test]
    public function appliedReportCarriesArtifactsAndNoReason(): void
    {
        $report = LoadReport::applied(['config' => ['settings.php']]);

        self::assertSame(LoadStatus::Applied, $report->status);
        self::assertSame(['config' => ['settings.php']], $report->artifacts);
        self::assertNull($report->reason);
        self::assertTrue($report->wasApplied());
    }

    #[Test]
    public function appliedReportMayCarryNoArtifacts(): void
    {
        $report = LoadReport::applied();

        self::assertSame(LoadStatus::Applied, $report->status);
        self::assertSame([], $report->artifacts);
        self::assertNull($report->reason);
    }

    #[Test]
    public function skippedReportCarriesReasonAndNoArtifacts(): void
    {
        $report = LoadReport::skipped(SkipReason::NoDirectory);

        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
        self::assertSame([], $report->artifacts);
        self::assertFalse($report->wasApplied());
    }

    #[Test]
    public function constructorRejectsAnAppliedReportThatCarriesAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An applied LoadReport must not carry a skip reason.');

        new LoadReport(LoadStatus::Applied, [], SkipReason::NoDirectory);
    }

    #[Test]
    public function constructorRequiresASkippedReportToCarryAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A skipped LoadReport must carry a skip reason.');

        new LoadReport(LoadStatus::Skipped);
    }

    #[Test]
    public function constructorRejectsArtifactsOnASkippedReport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A skipped LoadReport must not carry artifacts.');

        new LoadReport(LoadStatus::Skipped, ['config' => ['settings.php']], SkipReason::NoDirectory);
    }

    #[Test]
    public function toArrayOfAnAppliedReportExposesOnlyStatusAndArtifacts(): void
    {
        $report = LoadReport::applied(['routes' => ['web.php', 'api.php']]);

        self::assertSame([
            'status' => 'applied',
            'artifacts' => ['routes' => ['web.php', 'api.php']],
        ], $report->toArray());
    }

    #[Test]
    public function toArrayOfASkippedReportExposesStatusReasonAndEmptyArtifacts(): void
    {
        $report = LoadReport::skipped(SkipReason::EmptyDirectory);

        self::assertSame([
            'status' => 'skipped',
            'reason' => 'empty_directory',
            'artifacts' => [],
        ], $report->toArray());
    }

    #[Test]
    public function enumsExposeStableSerializableValues(): void
    {
        self::assertSame('applied', LoadStatus::Applied->value);
        self::assertSame('skipped', LoadStatus::Skipped->value);

        self::assertSame('no_directory', SkipReason::NoDirectory->value);
        self::assertSame('empty_directory', SkipReason::EmptyDirectory->value);
        self::assertSame('file_not_found', SkipReason::FileNotFound->value);
        self::assertSame('routes_cached', SkipReason::RoutesCached->value);
        self::assertSame('not_running_in_console', SkipReason::NotRunningInConsole->value);
    }
}
