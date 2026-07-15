<?php

declare(strict_types=1);

namespace TPanel\Notifications;

interface NotificationCommandRunner
{
    /**
     * @param list<string> $arguments
     */
    public function run(string $binaryPath, array $arguments, int $timeoutSeconds): NotificationCommandResult;
}
