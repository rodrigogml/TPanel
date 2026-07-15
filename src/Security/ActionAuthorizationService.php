<?php

declare(strict_types=1);

namespace TPanel\Security;

final class ActionAuthorizationService
{
    /**
     * @param array<string, mixed> $catalogEntry
     */
    public function canExecuteAdministrativeAction(
        AuthenticatedUser $actor,
        array $catalogEntry
    ): AuthorizationDecision {
        $baseDecision = $this->baseUserDecision($actor);

        if ($baseDecision !== null) {
            return $baseDecision;
        }

        if (
            $actor->role()->roleName() !== UserRole::ADMINISTRATOR
            || !$actor->role()->canRunAdministrativeAction()
        ) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::ROLE_NOT_ALLOWED,
                'Only Administrators can execute administrative actions.'
            );
        }

        if (($catalogEntry['enabled'] ?? false) !== true) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::ACTION_DISABLED,
                'Administrative action is disabled in the authorized catalog.'
            );
        }

        return AuthorizationDecision::allow();
    }

    public function canViewMonitoring(AuthenticatedUser $actor): AuthorizationDecision
    {
        return $this->baseUserDecision($actor) ?? AuthorizationDecision::allow();
    }

    public function canAcknowledgeAlert(AuthenticatedUser $actor): AuthorizationDecision
    {
        $baseDecision = $this->baseUserDecision($actor);

        if ($baseDecision !== null) {
            return $baseDecision;
        }

        if (!$actor->role()->canAcknowledgeAlert()) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::ROLE_NOT_ALLOWED,
                'Authenticated user cannot acknowledge alerts.'
            );
        }

        return AuthorizationDecision::allow();
    }

    public function canCommentEvent(AuthenticatedUser $actor): AuthorizationDecision
    {
        $baseDecision = $this->baseUserDecision($actor);

        if ($baseDecision !== null) {
            return $baseDecision;
        }

        if (!$actor->role()->canCommentEvent()) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::ROLE_NOT_ALLOWED,
                'Authenticated user cannot comment events.'
            );
        }

        return AuthorizationDecision::allow();
    }

    private function baseUserDecision(AuthenticatedUser $actor): ?AuthorizationDecision
    {
        if (!$actor->isActive()) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::USER_INACTIVE,
                'Authenticated user is inactive.'
            );
        }

        if (!$actor->role()->isKnown()) {
            return AuthorizationDecision::deny(
                AuthorizationDecision::ROLE_UNKNOWN,
                sprintf('Authenticated user has unknown role "%s".', $actor->role()->roleName())
            );
        }

        return null;
    }
}
