<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use PDO;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;

final class PdoAuthenticatedUserRepository implements AuthenticatedUserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByExternalUsername(string $externalUsername): ?AuthenticatedUser
    {
        $statement = $this->pdo->prepare(
            'SELECT
                au.id,
                au.externalUsername,
                au.displayName,
                au.isActive,
                ur.id AS roleId,
                ur.roleName,
                ur.description AS roleDescription,
                ur.canRunAdministrativeAction,
                ur.canAcknowledgeAlert,
                ur.canCommentEvent
            FROM authenticatedUser au
            INNER JOIN userRole ur ON ur.id = au.idUserRole
            WHERE au.externalUsername = :externalUsername
            LIMIT 1'
        );

        $statement->execute(['externalUsername' => $externalUsername]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new AuthenticatedUser(
            id: (int) $row['id'],
            externalUsername: (string) $row['externalUsername'],
            displayName: $row['displayName'] === null ? null : (string) $row['displayName'],
            isActive: (bool) $row['isActive'],
            role: new UserRole(
                id: (int) $row['roleId'],
                roleName: (string) $row['roleName'],
                description: (string) $row['roleDescription'],
                canRunAdministrativeAction: (bool) $row['canRunAdministrativeAction'],
                canAcknowledgeAlert: (bool) $row['canAcknowledgeAlert'],
                canCommentEvent: (bool) $row['canCommentEvent'],
            ),
        );
    }
}
