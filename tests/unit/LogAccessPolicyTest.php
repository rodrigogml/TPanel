<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\LogAccessDecision;
use TPanel\Security\LogAccessPolicy;
use TPanel\Security\UserRole;

final class LogAccessPolicyTest extends TestCase
{
    public function testAdministratorCanViewAllKnownLogSources(): void
    {
        $policy = new LogAccessPolicy();
        $admin = $this->user(UserRole::ADMINISTRATOR);

        foreach ([
            LogAccessPolicy::JOURNAL_RECENT_ERRORS,
            LogAccessPolicy::SYSLOG_RECENT_ERRORS,
            LogAccessPolicy::SECURITY_SUMMARY,
            LogAccessPolicy::AUTH_LOG_RAW,
            LogAccessPolicy::AUDIT_LOG,
            LogAccessPolicy::COMMAND_OUTPUT,
        ] as $source) {
            self::assertTrue($policy->canView($admin, $source)->allowed(), $source);
        }
    }

    public function testMonitorCanViewOnlySanitizedOperationalSummaries(): void
    {
        $policy = new LogAccessPolicy();
        $monitor = $this->user(UserRole::MONITOR);

        self::assertTrue($policy->canView($monitor, LogAccessPolicy::JOURNAL_RECENT_ERRORS)->allowed());
        self::assertTrue($policy->canView($monitor, LogAccessPolicy::SYSLOG_RECENT_ERRORS)->allowed());
        self::assertTrue($policy->canView($monitor, LogAccessPolicy::SECURITY_SUMMARY)->allowed());

        $authDecision = $policy->canView($monitor, LogAccessPolicy::AUTH_LOG_RAW);
        $auditDecision = $policy->canView($monitor, LogAccessPolicy::AUDIT_LOG);
        $commandDecision = $policy->canView($monitor, LogAccessPolicy::COMMAND_OUTPUT);

        self::assertFalse($authDecision->allowed());
        self::assertSame(LogAccessDecision::LOG_SOURCE_RESTRICTED, $authDecision->reasonCode());
        self::assertFalse($auditDecision->allowed());
        self::assertFalse($commandDecision->allowed());
    }

    public function testUnknownAndInactiveUsersAreDenied(): void
    {
        $policy = new LogAccessPolicy();

        self::assertSame(
            LogAccessDecision::ROLE_UNKNOWN,
            $policy->canView($this->user('CUSTOM'), LogAccessPolicy::JOURNAL_RECENT_ERRORS)->reasonCode()
        );
        self::assertSame(
            LogAccessDecision::USER_INACTIVE,
            $policy->canView($this->user(UserRole::MONITOR, isActive: false), LogAccessPolicy::JOURNAL_RECENT_ERRORS)->reasonCode()
        );
    }

    public function testUnknownLogSourceIsDenied(): void
    {
        $decision = (new LogAccessPolicy())->canView($this->user(UserRole::ADMINISTRATOR), 'kernel.raw');

        self::assertFalse($decision->allowed());
        self::assertSame(LogAccessDecision::LOG_SOURCE_UNKNOWN, $decision->reasonCode());
    }

    private function user(string $roleName, bool $isActive = true): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: 'actor.local',
            displayName: null,
            isActive: $isActive,
            role: new UserRole(
                id: 1,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $roleName === UserRole::ADMINISTRATOR,
                canAcknowledgeAlert: true,
                canCommentEvent: true,
            ),
        );
    }
}
