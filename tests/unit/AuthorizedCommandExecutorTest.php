<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TPanel\Command\AuthorizedCommandExecutor;
use TPanel\Command\AuthorizedCommandRequest;
use TPanel\Command\AuthorizedCommandResult;
use TPanel\Command\CommandIdempotencyPolicy;
use TPanel\Repositories\InMemoryCommandExecutionRepository;

final class AuthorizedCommandExecutorTest extends TestCase
{
    public function testExecutesAuthorizedCommandRequestSuccessfullyWithSanitizedOutput(): void
    {
        $executor = new AuthorizedCommandExecutor(summaryLimitBytes: 2048);
        $request = $this->request(
            requestId: 'req-success',
            code: 'fwrite(STDOUT, "service ok token=abc123\n");'
        );

        $result = $executor->execute($request);

        self::assertSame('req-success', $result->requestId());
        self::assertSame(AuthorizedCommandResult::SUCCESS, $result->resultStatus());
        self::assertSame(0, $result->exitCode());
        self::assertSame('service ok token=[REDACTED]', $result->stdoutSummary());
        self::assertNull($result->stderrSummary());
        self::assertNull($result->failureReason());
        self::assertNotNull($result->finishedAt());
    }

    public function testReportsCommandFailureWithSanitizedStderr(): void
    {
        $executor = new AuthorizedCommandExecutor();
        $request = $this->request(
            requestId: 'req-failed',
            code: 'fwrite(STDERR, "failed password=plain\n"); exit(7);'
        );

        $result = $executor->execute($request);

        self::assertSame(AuthorizedCommandResult::FAILED, $result->resultStatus());
        self::assertSame(7, $result->exitCode());
        self::assertSame('failed password=[REDACTED]', $result->stderrSummary());
        self::assertSame('Command exited with code 7.', $result->failureReason());
    }

    public function testReportsTimeoutAndTerminatesProcess(): void
    {
        $executor = new AuthorizedCommandExecutor();
        $request = $this->request(
            requestId: 'req-timeout',
            code: 'fwrite(STDOUT, "started\n"); sleep(3);',
            timeoutSeconds: 1
        );

        $result = $executor->execute($request);

        self::assertSame(AuthorizedCommandResult::TIMED_OUT, $result->resultStatus());
        self::assertNull($result->exitCode());
        self::assertSame('started', $result->stdoutSummary());
        self::assertSame('Command exceeded timeout of 1 seconds.', $result->failureReason());
        self::assertNotNull($result->finishedAt());
    }

    public function testRetryWithSameRequestIdReturnsPreviousResultWithoutReexecution(): void
    {
        $markerPath = sys_get_temp_dir() . '/tpanel-idempotency-' . bin2hex(random_bytes(8));
        $executor = new AuthorizedCommandExecutor();
        $request = $this->request(
            requestId: 'req-retry',
            code: sprintf(
                'file_put_contents(%s, "x", FILE_APPEND); fwrite(STDOUT, file_get_contents(%s));',
                var_export($markerPath, true),
                var_export($markerPath, true)
            )
        );

        $first = $executor->execute($request);
        $second = $executor->execute($request);

        self::assertSame(AuthorizedCommandResult::SUCCESS, $first->resultStatus());
        self::assertSame(AuthorizedCommandResult::SUCCESS, $second->resultStatus());
        self::assertSame($first->stdoutSummary(), $second->stdoutSummary());
        self::assertSame('x', file_get_contents($markerPath));

        @unlink($markerPath);
    }

    public function testDuplicateRequestIdWithDifferentPayloadIsDenied(): void
    {
        $executor = new AuthorizedCommandExecutor();

        $first = $executor->execute($this->request(
            requestId: 'req-conflict',
            code: 'fwrite(STDOUT, "first\n");'
        ));
        $second = $executor->execute($this->request(
            requestId: 'req-conflict',
            code: 'fwrite(STDOUT, "second\n");'
        ));

        self::assertSame(AuthorizedCommandResult::SUCCESS, $first->resultStatus());
        self::assertSame(AuthorizedCommandResult::DENIED, $second->resultStatus());
        self::assertSame('Duplicate requestId was reused with different command details.', $second->failureReason());
    }

    public function testDuplicateInProgressRequestIsDeniedWithoutExecution(): void
    {
        $repository = new InMemoryCommandExecutionRepository();
        $policy = new CommandIdempotencyPolicy(windowSeconds: 900);
        $executor = new AuthorizedCommandExecutor(
            executionRepository: $repository,
            idempotencyPolicy: $policy
        );
        $request = $this->request(
            requestId: 'req-in-progress',
            code: 'fwrite(STDOUT, "should not run\n");'
        );
        $now = new DateTimeImmutable();
        $repository->reserve(
            requestId: $request->requestId(),
            actionKey: $request->actionKey(),
            requestFingerprint: $this->fingerprint($request),
            expiresAt: $now->modify('+900 seconds'),
            now: $now,
        );

        $result = $executor->execute($request);

        self::assertSame(AuthorizedCommandResult::DENIED, $result->resultStatus());
        self::assertSame('Duplicate request is already in progress.', $result->failureReason());
        self::assertNull($result->stdoutSummary());
    }

    public function testRejectsInvalidAuthorizedCommandRequestBeforeExecution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorized command request field "requestId" is required.');

        AuthorizedCommandRequest::fromCatalog(
            requestId: '',
            actorUsername: 'admin.local',
            actionKey: 'test.php',
            catalogEntry: $this->catalogEntry(timeoutSeconds: 1),
            validatedParameters: ['mode' => '-r', 'code' => 'echo "never";'],
            auditContext: ['sourcePage' => 'unit-test']
        );
    }

    public function testBuildsSudoProcessArgumentsWhenRunAsUserIsConfigured(): void
    {
        $request = AuthorizedCommandRequest::fromCatalog(
            requestId: 'req-sudo',
            actorUsername: 'admin.local',
            actionKey: 'service.status',
            catalogEntry: [
                'commandKey' => 'systemctl-status',
                'executablePath' => '/opt/tpanel/scripts/system/service-status',
                'timeoutSeconds' => 5,
                'runAsUser' => 'tpanel',
            ],
            validatedParameters: ['serviceName' => 'apache2.service'],
            auditContext: ['sourcePage' => 'unit-test']
        );

        self::assertSame(
            ['/usr/bin/sudo', '-n', '-u', 'tpanel', '--', '/opt/tpanel/scripts/system/service-status', 'apache2.service'],
            $request->processArguments()
        );
    }

    private function request(string $requestId, string $code, int $timeoutSeconds = 5): AuthorizedCommandRequest
    {
        return AuthorizedCommandRequest::fromCatalog(
            requestId: $requestId,
            actorUsername: 'admin.local',
            actionKey: 'test.php',
            catalogEntry: $this->catalogEntry($timeoutSeconds),
            validatedParameters: [
                'mode' => '-r',
                'code' => $code,
            ],
            auditContext: ['sourcePage' => 'unit-test']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogEntry(int $timeoutSeconds): array
    {
        return [
            'commandKey' => 'php-inline-test',
            'executablePath' => PHP_BINARY,
            'timeoutSeconds' => $timeoutSeconds,
        ];
    }

    private function fingerprint(AuthorizedCommandRequest $request): string
    {
        return hash('sha256', json_encode([
            'actorUsername' => $request->actorUsername(),
            'actionKey' => $request->actionKey(),
            'commandKey' => $request->commandKey(),
            'executablePath' => $request->executablePath(),
            'runAsUser' => $request->runAsUser(),
            'parameters' => $request->parameters(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
