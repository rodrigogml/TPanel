<?php

declare(strict_types=1);

namespace TPanel\Security;

final class LogAccessPolicy
{
    public const JOURNAL_RECENT_ERRORS = 'journal.recent-errors';
    public const SYSLOG_RECENT_ERRORS = 'syslog.recent-errors';
    public const SECURITY_SUMMARY = 'security.summary';
    public const AUTH_LOG_RAW = 'auth.raw';
    public const AUDIT_LOG = 'audit.records';
    public const COMMAND_OUTPUT = 'command.output';

    /** @var list<string> */
    private const KNOWN_SOURCES = [
        self::JOURNAL_RECENT_ERRORS,
        self::SYSLOG_RECENT_ERRORS,
        self::SECURITY_SUMMARY,
        self::AUTH_LOG_RAW,
        self::AUDIT_LOG,
        self::COMMAND_OUTPUT,
    ];

    /** @var list<string> */
    private const MONITOR_ALLOWED_SOURCES = [
        self::JOURNAL_RECENT_ERRORS,
        self::SYSLOG_RECENT_ERRORS,
        self::SECURITY_SUMMARY,
    ];

    public function canView(AuthenticatedUser $actor, string $logSource): LogAccessDecision
    {
        if (!$actor->isActive()) {
            return LogAccessDecision::deny(LogAccessDecision::USER_INACTIVE, 'Authenticated user is inactive.');
        }

        if (!$actor->role()->isKnown()) {
            return LogAccessDecision::deny(
                LogAccessDecision::ROLE_UNKNOWN,
                sprintf('Authenticated user has unknown role "%s".', $actor->role()->roleName())
            );
        }

        if (!in_array($logSource, self::KNOWN_SOURCES, true)) {
            return LogAccessDecision::deny(
                LogAccessDecision::LOG_SOURCE_UNKNOWN,
                sprintf('Log source "%s" is not known.', $logSource)
            );
        }

        if ($actor->role()->roleName() === UserRole::ADMINISTRATOR) {
            return LogAccessDecision::allow();
        }

        if (in_array($logSource, self::MONITOR_ALLOWED_SOURCES, true)) {
            return LogAccessDecision::allow();
        }

        return LogAccessDecision::deny(
            LogAccessDecision::LOG_SOURCE_RESTRICTED,
            sprintf('Log source "%s" is restricted to Administrators.', $logSource)
        );
    }

    /**
     * @return list<string>
     */
    public function monitorAllowedSources(): array
    {
        return self::MONITOR_ALLOWED_SOURCES;
    }
}
