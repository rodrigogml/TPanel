<?php

declare(strict_types=1);

namespace TPanel\Command;

use DateTimeImmutable;
use TPanel\Audit\AuditDataSanitizer;
use TPanel\Repositories\CommandExecutionRepository;
use TPanel\Repositories\InMemoryCommandExecutionRepository;

final class AuthorizedCommandExecutor
{
    public function __construct(
        private readonly CommandParameterValidator $parameterValidator = new CommandParameterValidator(),
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
        private readonly int $summaryLimitBytes = 4096,
        private readonly CommandExecutionRepository $executionRepository = new InMemoryCommandExecutionRepository(),
        private readonly CommandIdempotencyPolicy $idempotencyPolicy = new CommandIdempotencyPolicy(),
    ) {
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $catalogEntry
     * @param array<string, mixed> $parameters
     */
    public function validateParameters(
        array $catalogEntry,
        array $parameters
    ): CommandParameterValidationResult {
        return $this->parameterValidator->validate($catalogEntry, $parameters);
    }

    public function execute(AuthorizedCommandRequest $request): AuthorizedCommandResult
    {
        $startedAt = new DateTimeImmutable();
        $fingerprint = $this->fingerprint($request);
        $reservation = $this->executionRepository->reserve(
            requestId: $request->requestId(),
            actionKey: $request->actionKey(),
            requestFingerprint: $fingerprint,
            expiresAt: $startedAt->modify(sprintf('+%d seconds', $this->idempotencyPolicy->windowSeconds())),
            now: $startedAt,
        );

        if (!$reservation->created()) {
            return $this->resultForDuplicate($reservation->record(), $fingerprint, $startedAt);
        }

        $result = $this->executeReserved($request, $startedAt);
        $this->executionRepository->complete($request->requestId(), $result, new DateTimeImmutable());

        return $result;
    }

    private function executeReserved(
        AuthorizedCommandRequest $request,
        DateTimeImmutable $startedAt
    ): AuthorizedCommandResult {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($request->processArguments(), $descriptors, $pipes);

        if (!is_resource($process)) {
            return new AuthorizedCommandResult(
                requestId: $request->requestId(),
                resultStatus: AuthorizedCommandResult::FAILED,
                exitCode: null,
                stdoutSummary: null,
                stderrSummary: null,
                failureReason: 'Command process could not be started.',
                startedAt: $startedAt,
                finishedAt: new DateTimeImmutable(),
            );
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $request->timeoutSeconds();
        $timedOut = false;

        do {
            $stdout .= $this->readAvailable($pipes[1]);
            $stderr .= $this->readAvailable($pipes[2]);
            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                usleep(100000);
                $status = proc_get_status($process);

                if ($status['running']) {
                    proc_terminate($process, 9);
                }

                break;
            }

            usleep(10000);
        } while (true);

        $stdout .= $this->readAvailable($pipes[1]);
        $stderr .= $this->readAvailable($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $finishedAt = new DateTimeImmutable();

        if ($timedOut) {
            return new AuthorizedCommandResult(
                requestId: $request->requestId(),
                resultStatus: AuthorizedCommandResult::TIMED_OUT,
                exitCode: null,
                stdoutSummary: $this->summarize($stdout),
                stderrSummary: $this->summarize($stderr),
                failureReason: sprintf('Command exceeded timeout of %d seconds.', $request->timeoutSeconds()),
                startedAt: $startedAt,
                finishedAt: $finishedAt,
            );
        }

        if ($exitCode !== 0) {
            return new AuthorizedCommandResult(
                requestId: $request->requestId(),
                resultStatus: AuthorizedCommandResult::FAILED,
                exitCode: $exitCode,
                stdoutSummary: $this->summarize($stdout),
                stderrSummary: $this->summarize($stderr),
                failureReason: sprintf('Command exited with code %d.', $exitCode),
                startedAt: $startedAt,
                finishedAt: $finishedAt,
            );
        }

        return new AuthorizedCommandResult(
            requestId: $request->requestId(),
            resultStatus: AuthorizedCommandResult::SUCCESS,
            exitCode: 0,
            stdoutSummary: $this->summarize($stdout),
            stderrSummary: $this->summarize($stderr),
            failureReason: null,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );
    }

    private function resultForDuplicate(
        CommandExecutionRecord $record,
        string $fingerprint,
        DateTimeImmutable $occurredAt
    ): AuthorizedCommandResult {
        if ($record->requestFingerprint() !== $fingerprint) {
            return new AuthorizedCommandResult(
                requestId: $record->requestId(),
                resultStatus: AuthorizedCommandResult::DENIED,
                exitCode: null,
                stdoutSummary: null,
                stderrSummary: null,
                failureReason: 'Duplicate requestId was reused with different command details.',
                startedAt: $occurredAt,
                finishedAt: $occurredAt,
            );
        }

        if ($record->isInProgress()) {
            return new AuthorizedCommandResult(
                requestId: $record->requestId(),
                resultStatus: AuthorizedCommandResult::DENIED,
                exitCode: null,
                stdoutSummary: null,
                stderrSummary: null,
                failureReason: 'Duplicate request is already in progress.',
                startedAt: $occurredAt,
                finishedAt: $occurredAt,
            );
        }

        return $record->toResult();
    }

    /**
     * @param resource $pipe
     */
    private function readAvailable(mixed $pipe): string
    {
        $buffer = '';

        while (!feof($pipe)) {
            $chunk = fread($pipe, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function summarize(string $output): ?string
    {
        $sanitized = $this->sanitizer->sanitizeText($output);

        if ($sanitized === null || $sanitized === '') {
            return null;
        }

        if (strlen($sanitized) <= $this->summaryLimitBytes) {
            return $sanitized;
        }

        return substr($sanitized, 0, $this->summaryLimitBytes) . '... [truncated]';
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
