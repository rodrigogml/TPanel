<?php

declare(strict_types=1);

namespace TPanel\Notifications;

final class LocalNotificationCommandRunner implements NotificationCommandRunner
{
    public function run(string $binaryPath, array $arguments, int $timeoutSeconds): NotificationCommandResult
    {
        $process = proc_open(
            array_merge([$binaryPath], $arguments),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return new NotificationCommandResult(1, null, 'Unable to start NotiCLI process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);

        do {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return new NotificationCommandResult(124, $stdout, $stderr, timedOut: true);
            }

            usleep(10000);
        } while (true);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return new NotificationCommandResult($exitCode, $stdout, $stderr);
    }
}
