<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Collectors\MonitoringCollectorService;
use TPanel\Collectors\ScheduleCollector;

require_once __DIR__ . '/FakeSystemDataSource.php';

final class ScheduleCollectorTest extends TestCase
{
    public function testScheduleCollectorParsesCronJobsAndSystemdTimers(): void
    {
        $collector = new ScheduleCollector(new FakeSystemDataSource(
            files: [
                '/etc/crontab' => implode("\n", [
                    '# system crontab',
                    'SHELL=/bin/sh',
                    '*/5 * * * * root /usr/local/bin/check-health --token=plain',
                    '@daily backup /usr/local/bin/backup-run',
                ]),
                '/etc/cron.d/tpanel' => '15 2 * * * tpanel /opt/tpanel/bin/collector',
            ],
            commands: [
                '/bin/ls -1 /etc/cron.d' => "tpanel\n.invalid/name\n",
                '/usr/bin/systemctl list-timers --all --output=json --no-pager' => json_encode([
                    [
                        'unit' => 'apt-daily.timer',
                        'activates' => 'apt-daily.service',
                        'next' => 'Wed 2026-07-15 14:30:00 -03',
                        'last' => 'Wed 2026-07-15 13:30:00 -03',
                        'active' => 'active',
                    ],
                ], JSON_THROW_ON_ERROR),
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:10:00'));

        self::assertTrue($snapshot->available);
        self::assertTrue($snapshot->cronAvailable);
        self::assertTrue($snapshot->timerAvailable);
        self::assertCount(3, $snapshot->cronJobs);
        self::assertSame('*/5 * * * *', $snapshot->cronJobs[0]['schedule']);
        self::assertSame('root', $snapshot->cronJobs[0]['user']);
        self::assertSame('/usr/local/bin/check-health --token=[REDACTED]', $snapshot->cronJobs[0]['command']);
        self::assertSame('@daily', $snapshot->cronJobs[1]['schedule']);
        self::assertSame('/etc/cron.d/tpanel', $snapshot->cronJobs[2]['source']);
        self::assertSame('apt-daily.timer', $snapshot->timers[0]['unit']);
        self::assertSame('apt-daily.service', $snapshot->timers[0]['activates']);
        self::assertSame('active', $snapshot->timers[0]['state']);
    }

    public function testScheduleCollectorTreatsMissingCronAndTimerSourcesAsUnavailable(): void
    {
        $collector = new ScheduleCollector(new FakeSystemDataSource());

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:11:00'));

        self::assertFalse($snapshot->available);
        self::assertFalse($snapshot->cronAvailable);
        self::assertFalse($snapshot->timerAvailable);
        self::assertSame([], $snapshot->cronJobs);
        self::assertSame([], $snapshot->timers);
    }

    public function testScheduleCollectorFallsBackToSystemctlTimerTableOutput(): void
    {
        $collector = new ScheduleCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/systemctl list-timers --all --output=json --no-pager' => implode("\n", [
                'Wed 2026-07-15 14:30:00 -03 20min left Wed 2026-07-15 13:30:00 -03 40min ago apt-daily.timer apt-daily.service',
                '- - - - tpanel.timer tpanel.service',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:12:00'));

        self::assertTrue($snapshot->timerAvailable);
        self::assertSame('apt-daily.timer', $snapshot->timers[0]['unit']);
        self::assertSame('apt-daily.service', $snapshot->timers[0]['activates']);
        self::assertSame('tpanel.timer', $snapshot->timers[1]['unit']);
        self::assertSame('tpanel.service', $snapshot->timers[1]['activates']);
    }

    public function testMonitoringCollectorServiceBuildsScheduleDraft(): void
    {
        $dataSource = new FakeSystemDataSource(
            files: [
                '/etc/crontab' => '0 * * * * root /usr/local/bin/hourly',
            ],
            commands: [
                '/usr/bin/systemctl list-timers --all --output=json --no-pager' => '[]',
            ]
        );
        $service = new MonitoringCollectorService(
            scheduleCollector: new ScheduleCollector($dataSource),
        );

        $drafts = $service->collectSchedules(new DateTimeImmutable('2026-07-15 14:13:00'));

        self::assertCount(1, $drafts);
        self::assertSame('SCHEDULE', $drafts[0]->metricCategory);
        self::assertSame('cron-and-timers', $drafts[0]->metricName);
        self::assertSame('NORMAL', $drafts[0]->severity);
        self::assertTrue($drafts[0]->metricValue['cronAvailable']);
        self::assertTrue($drafts[0]->metricValue['timerAvailable']);
    }
}
