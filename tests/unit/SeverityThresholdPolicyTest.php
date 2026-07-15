<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Monitoring\SeverityThresholdPolicy;

final class SeverityThresholdPolicyTest extends TestCase
{
    public function testClassifiesNormalWarningCriticalAndUnavailableForCpu(): void
    {
        $policy = new SeverityThresholdPolicy();

        self::assertSame(SeverityThresholdPolicy::NORMAL, $policy->classifyCpu(20.0));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyCpu(80.0));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyCpu(95.0));
        self::assertSame(SeverityThresholdPolicy::UNAVAILABLE, $policy->classifyCpu(null));
    }

    public function testUsesConfigurableThresholdOverrides(): void
    {
        $policy = new SeverityThresholdPolicy([
            'cpu' => [
                'usagePercentWarning' => 50.0,
                'usagePercentCritical' => 60.0,
            ],
        ]);

        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyCpu(55.0));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyCpu(65.0));
    }

    public function testClassifiesStorageByWorstFilesystemOrInodeUse(): void
    {
        $policy = new SeverityThresholdPolicy();

        self::assertSame(SeverityThresholdPolicy::UNAVAILABLE, $policy->classifyStorage(false, []));
        self::assertSame(SeverityThresholdPolicy::NORMAL, $policy->classifyStorage(true, [
            ['usedPercent' => 30.0, 'inodeUsedPercent' => null],
        ]));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyStorage(true, [
            ['usedPercent' => 85.0, 'inodeUsedPercent' => 10.0],
        ]));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyStorage(true, [
            ['usedPercent' => 10.0, 'inodeUsedPercent' => 95.0],
        ]));
    }

    public function testClassifiesDiskHealthRaidNetworkServicesAndDocker(): void
    {
        $policy = new SeverityThresholdPolicy();

        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyDiskHealth(true, [
            ['healthStatus' => 'FAILED', 'temperatureCelsius' => 40, 'reallocatedSectors' => 0, 'criticalErrors' => 0],
        ]));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyRaid(true, [
            ['state' => 'SYNCING', 'degradedDisks' => 0],
        ]));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyRaid(true, [
            ['state' => 'DEGRADED', 'degradedDisks' => 1],
        ]));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyNetwork(true, [
            ['rxErrors' => 2, 'txErrors' => 0],
        ], 20.0));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyNetwork(true, [], 300.0));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyServices(true, [
            ['activeState' => 'INACTIVE', 'subState' => 'DEAD'],
        ]));
        self::assertSame(SeverityThresholdPolicy::CRITICAL, $policy->classifyServices(true, [
            ['activeState' => 'FAILED', 'subState' => 'FAILED'],
        ]));
        self::assertSame(SeverityThresholdPolicy::WARNING, $policy->classifyDocker(true, [
            ['state' => 'EXITED'],
        ]));
        self::assertSame(SeverityThresholdPolicy::UNAVAILABLE, $policy->classifyDocker(false, []));
    }
}
