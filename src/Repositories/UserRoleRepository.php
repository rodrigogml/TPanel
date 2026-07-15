<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use TPanel\Security\UserRole;

interface UserRoleRepository
{
    public function findByRoleName(string $roleName): ?UserRole;
}
