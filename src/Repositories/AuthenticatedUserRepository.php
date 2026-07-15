<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use TPanel\Security\AuthenticatedUser;

interface AuthenticatedUserRepository
{
    public function findByExternalUsername(string $externalUsername): ?AuthenticatedUser;
}
