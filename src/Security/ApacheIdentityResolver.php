<?php

declare(strict_types=1);

namespace TPanel\Security;

use TPanel\Repositories\AuthenticatedUserRepository;

final class ApacheIdentityResolver
{
    public function __construct(
        private readonly AuthenticatedUserRepository $users
    ) {
    }

    public function resolve(?string $apacheUsername): AuthenticatedUser
    {
        $username = trim((string) $apacheUsername);

        if ($username === '') {
            throw IdentityMappingException::missingApacheIdentity();
        }

        $user = $this->users->findByExternalUsername($username);

        if ($user === null) {
            throw IdentityMappingException::unknownUser($username);
        }

        if (!$user->isActive()) {
            throw IdentityMappingException::inactiveUser($username);
        }

        if (!$user->role()->isKnown()) {
            throw IdentityMappingException::unknownRole($username, $user->role()->roleName());
        }

        return $user;
    }
}
