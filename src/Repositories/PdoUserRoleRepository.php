<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use PDO;
use TPanel\Security\UserRole;

final class PdoUserRoleRepository implements UserRoleRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByRoleName(string $roleName): ?UserRole
    {
        $statement = $this->pdo->prepare(
            'SELECT
                id,
                roleName,
                description,
                canRunAdministrativeAction,
                canAcknowledgeAlert,
                canCommentEvent
            FROM userRole
            WHERE roleName = :roleName
            LIMIT 1'
        );

        $statement->execute(['roleName' => $roleName]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new UserRole(
            id: (int) $row['id'],
            roleName: (string) $row['roleName'],
            description: (string) $row['description'],
            canRunAdministrativeAction: (bool) $row['canRunAdministrativeAction'],
            canAcknowledgeAlert: (bool) $row['canAcknowledgeAlert'],
            canCommentEvent: (bool) $row['canCommentEvent'],
        );
    }
}
