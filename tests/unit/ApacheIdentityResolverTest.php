<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Repositories\AuthenticatedUserRepository;
use TPanel\Security\ApacheIdentityResolver;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\IdentityMappingException;
use TPanel\Security\UserRole;

final class ApacheIdentityResolverTest extends TestCase
{
    public function testResolvesAdministratorIdentity(): void
    {
        $resolver = new ApacheIdentityResolver(new InMemoryAuthenticatedUserRepository([
            self::user('admin.local', UserRole::ADMINISTRATOR, true, true),
        ]));

        $user = $resolver->resolve('admin.local');

        self::assertSame('admin.local', $user->externalUsername());
        self::assertSame(UserRole::ADMINISTRATOR, $user->role()->roleName());
        self::assertTrue($user->role()->canRunAdministrativeAction());
    }

    public function testResolvesMonitorIdentityWithoutAdministrativeActionCapability(): void
    {
        $resolver = new ApacheIdentityResolver(new InMemoryAuthenticatedUserRepository([
            self::user('monitor.local', UserRole::MONITOR, false, true),
        ]));

        $user = $resolver->resolve('monitor.local');

        self::assertSame(UserRole::MONITOR, $user->role()->roleName());
        self::assertFalse($user->role()->canRunAdministrativeAction());
        self::assertTrue($user->role()->canAcknowledgeAlert());
        self::assertTrue($user->role()->canCommentEvent());
    }

    public function testRejectsUnknownApacheIdentity(): void
    {
        $resolver = new ApacheIdentityResolver(new InMemoryAuthenticatedUserRepository([]));

        $this->expectException(IdentityMappingException::class);
        $this->expectExceptionMessage('Authenticated user "unknown.local" is not registered in TPanel.');

        $resolver->resolve('unknown.local');
    }

    public function testRejectsInactiveUser(): void
    {
        $resolver = new ApacheIdentityResolver(new InMemoryAuthenticatedUserRepository([
            self::user('inactive.local', UserRole::MONITOR, false, false),
        ]));

        $this->expectException(IdentityMappingException::class);
        $this->expectExceptionMessage('Authenticated user "inactive.local" is inactive in TPanel.');

        $resolver->resolve('inactive.local');
    }

    public function testRejectsUnknownRole(): void
    {
        $resolver = new ApacheIdentityResolver(new InMemoryAuthenticatedUserRepository([
            self::user('custom.local', 'CUSTOM', false, true),
        ]));

        $this->expectException(IdentityMappingException::class);
        $this->expectExceptionMessage('Authenticated user "custom.local" has unknown role "CUSTOM".');

        $resolver->resolve('custom.local');
    }

    private static function user(
        string $username,
        string $roleName,
        bool $canRunAdministrativeAction,
        bool $isActive
    ): AuthenticatedUser {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: $username,
            displayName: null,
            isActive: $isActive,
            role: new UserRole(
                id: 1,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $canRunAdministrativeAction,
                canAcknowledgeAlert: true,
                canCommentEvent: true,
            ),
        );
    }
}

/**
 * @phpstan-type UserMap array<string, AuthenticatedUser>
 */
final class InMemoryAuthenticatedUserRepository implements AuthenticatedUserRepository
{
    /** @var array<string, AuthenticatedUser> */
    private array $users;

    /**
     * @param list<AuthenticatedUser> $users
     */
    public function __construct(array $users)
    {
        $this->users = [];

        foreach ($users as $user) {
            $this->users[$user->externalUsername()] = $user;
        }
    }

    public function findByExternalUsername(string $externalUsername): ?AuthenticatedUser
    {
        return $this->users[$externalUsername] ?? null;
    }
}
