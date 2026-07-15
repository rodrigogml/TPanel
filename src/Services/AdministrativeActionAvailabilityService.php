<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Security\ActionAuthorizationService;
use TPanel\Security\AuthenticatedUser;

final class AdministrativeActionAvailabilityService
{
    public function __construct(
        private readonly ActionAuthorizationService $authorizationService = new ActionAuthorizationService(),
    ) {
    }

    /**
     * @param array<string, mixed> $commandCatalog
     * @return list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>
     */
    public function allowedForTarget(
        AuthenticatedUser $actor,
        array $commandCatalog,
        string $targetType
    ): array {
        $actions = [];

        foreach (($commandCatalog['commands'] ?? []) as $actionKey => $entry) {
            if (!is_array($entry) || ($entry['targetType'] ?? null) !== $targetType) {
                continue;
            }

            $decision = $this->authorizationService->canExecuteAdministrativeAction($actor, $entry);

            if (!$decision->allowed()) {
                continue;
            }

            $actions[] = [
                'actionKey' => (string) $actionKey,
                'targetType' => $targetType,
                'displayName' => (string) ($entry['displayName'] ?? $actionKey),
                'requiresConfirmation' => ($entry['requiresConfirmation'] ?? true) === true,
                'timeoutSeconds' => isset($entry['timeoutSeconds']) ? (int) $entry['timeoutSeconds'] : null,
            ];
        }

        return $actions;
    }
}
