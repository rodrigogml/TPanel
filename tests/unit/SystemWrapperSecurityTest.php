<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SystemWrapperSecurityTest extends TestCase
{
    public function testEveryCatalogExecutableHasRepositoryWrapper(): void
    {
        $catalog = require __DIR__ . '/../../config/commands.php.model';

        foreach ($catalog['commands'] as $actionKey => $entry) {
            $wrapper = __DIR__ . '/../../scripts/system/' . basename($entry['executablePath']);

            self::assertFileExists($wrapper, sprintf('Missing wrapper for action "%s".', $actionKey));
            self::assertTrue(is_executable($wrapper), sprintf('Wrapper for action "%s" must be executable.', $actionKey));
        }
    }

    public function testSudoersModelMapsOnlyCatalogWrapperPaths(): void
    {
        $catalog = require __DIR__ . '/../../config/commands.php.model';
        $sudoers = file_get_contents(__DIR__ . '/../../scripts/sudoers/tpanel.model');

        self::assertIsString($sudoers);
        self::assertStringNotContainsString('NOPASSWD: ALL', $sudoers);
        self::assertStringNotContainsString('(ALL)', $sudoers);
        self::assertStringNotContainsString('/bin/bash', $sudoers);
        self::assertStringNotContainsString('/bin/sh', $sudoers);
        self::assertStringNotContainsString('/usr/bin/env', $sudoers);

        foreach ($catalog['commands'] as $entry) {
            self::assertStringContainsString($entry['executablePath'] . ' *', $sudoers);
        }
    }

    public function testWrappersRejectShellInjectionLikeParameters(): void
    {
        $cases = [
            ['service-status', ['apache2.service;id']],
            ['service-restart', ['apache2.service;id']],
            ['service-reload', ['apache2.service;id']],
            ['docker-container-status', ['web;id']],
            ['docker-container-restart', ['web;id']],
            ['timer-status', ['backup.service;id']],
            ['timer-restart', ['backup.service;id']],
            ['collector-once', ['SYSTEM;id']],
            ['noticli-test', ['service;id', 'HIGH']],
        ];

        foreach ($cases as [$wrapperName, $arguments]) {
            $result = $this->runWrapper($wrapperName, $arguments);

            self::assertSame(64, $result['exitCode'], sprintf('%s should reject malformed parameters.', $wrapperName));
            self::assertStringContainsString('invalid', $result['stderr']);
            self::assertStringNotContainsString('uid=', $result['stdout'] . $result['stderr']);
        }
    }

    public function testWrappersRejectExtraParameters(): void
    {
        $cases = [
            ['service-status', ['apache2.service', 'extra']],
            ['docker-container-status', ['web', 'extra']],
            ['timer-status', ['backup.timer', 'extra']],
            ['collector-once', ['CPU', 'extra']],
            ['noticli-test', ['service', 'HIGH', 'extra']],
            ['memory-swap-reload', ['extra']],
        ];

        foreach ($cases as [$wrapperName, $arguments]) {
            $result = $this->runWrapper($wrapperName, $arguments);

            self::assertSame(64, $result['exitCode'], sprintf('%s should reject extra parameters.', $wrapperName));
            self::assertStringContainsString('usage:', $result['stderr']);
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runWrapper(string $wrapperName, array $arguments): array
    {
        $script = __DIR__ . '/../../scripts/system/' . $wrapperName;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(array_merge([$script], $arguments), $descriptors, $pipes);

        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }
}
