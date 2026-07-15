<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Security\ActionAuthorizationService;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\AuthorizationDecision;
use TPanel\Security\UserRole;

final class ActionAuthorizationServiceTest extends TestCase
{
    public function testAdministratorCanExecuteEnabledAdministrativeAction(): void
    {
        $service = new ActionAuthorizationService();
        $catalogEntry = $this->catalogEntry('service.status');

        $decision = $service->canExecuteAdministrativeAction(
            $this->user(UserRole::ADMINISTRATOR),
            $catalogEntry
        );

        self::assertTrue($decision->allowed());
        self::assertSame(AuthorizationDecision::ALLOWED, $decision->reasonCode());
    }

    public function testMonitorCannotExecuteAnyAdministrativeCatalogAction(): void
    {
        $service = new ActionAuthorizationService();

        foreach ($this->catalog()['commands'] as $actionKey => $catalogEntry) {
            $catalogEntry['enabled'] = true;

            $decision = $service->canExecuteAdministrativeAction(
                $this->user(UserRole::MONITOR),
                $catalogEntry
            );

            self::assertFalse($decision->allowed(), sprintf('Action "%s" should be denied.', $actionKey));
            self::assertSame(AuthorizationDecision::ROLE_NOT_ALLOWED, $decision->reasonCode());
        }
    }

    public function testMonitorCanOnlyUseReadOrEventCapabilities(): void
    {
        $service = new ActionAuthorizationService();
        $monitor = $this->user(UserRole::MONITOR);

        self::assertTrue($service->canViewMonitoring($monitor)->allowed());
        self::assertTrue($service->canAcknowledgeAlert($monitor)->allowed());
        self::assertTrue($service->canCommentEvent($monitor)->allowed());
        self::assertFalse($service->canExecuteAdministrativeAction(
            $monitor,
            ['enabled' => true, 'requiresAdministrator' => true]
        )->allowed());
    }

    public function testAdministratorCannotExecuteDisabledAdministrativeAction(): void
    {
        $service = new ActionAuthorizationService();
        $catalogEntry = $this->catalogEntry('service.restart');

        $decision = $service->canExecuteAdministrativeAction(
            $this->user(UserRole::ADMINISTRATOR),
            $catalogEntry
        );

        self::assertFalse($decision->allowed());
        self::assertSame(AuthorizationDecision::ACTION_DISABLED, $decision->reasonCode());
    }

    public function testInactiveUserIsDeniedBeforeCapabilities(): void
    {
        $service = new ActionAuthorizationService();
        $actor = $this->user(UserRole::ADMINISTRATOR, isActive: false);

        $decision = $service->canExecuteAdministrativeAction(
            $actor,
            $this->catalogEntry('service.status')
        );

        self::assertFalse($decision->allowed());
        self::assertSame(AuthorizationDecision::USER_INACTIVE, $decision->reasonCode());
        self::assertSame(AuthorizationDecision::USER_INACTIVE, $service->canViewMonitoring($actor)->reasonCode());
    }

    public function testUnknownRoleIsDeniedForAllCapabilities(): void
    {
        $service = new ActionAuthorizationService();
        $actor = $this->user('CUSTOM', canRunAdministrativeAction: true);

        self::assertSame(
            AuthorizationDecision::ROLE_UNKNOWN,
            $service->canExecuteAdministrativeAction($actor, $this->catalogEntry('service.status'))->reasonCode()
        );
        self::assertSame(AuthorizationDecision::ROLE_UNKNOWN, $service->canViewMonitoring($actor)->reasonCode());
        self::assertSame(AuthorizationDecision::ROLE_UNKNOWN, $service->canAcknowledgeAlert($actor)->reasonCode());
        self::assertSame(AuthorizationDecision::ROLE_UNKNOWN, $service->canCommentEvent($actor)->reasonCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogEntry(string $actionKey): array
    {
        $catalog = $this->catalog();

        self::assertArrayHasKey($actionKey, $catalog['commands']);

        return $catalog['commands'][$actionKey];
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>}
     */
    private function catalog(): array
    {
        return require __DIR__ . '/../../config/commands.php.model';
    }

    private function user(
        string $roleName,
        bool $canRunAdministrativeAction = false,
        bool $canAcknowledgeAlert = true,
        bool $canCommentEvent = true,
        bool $isActive = true
    ): AuthenticatedUser {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: 'actor.local',
            displayName: null,
            isActive: $isActive,
            role: new UserRole(
                id: 1,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $roleName === UserRole::ADMINISTRATOR || $canRunAdministrativeAction,
                canAcknowledgeAlert: $canAcknowledgeAlert,
                canCommentEvent: $canCommentEvent,
            ),
        );
    }
}
