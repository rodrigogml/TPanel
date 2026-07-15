<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Audit\AuditDataSanitizer;
use TPanel\Notifications\LocalNotificationCommandRunner;
use TPanel\Notifications\NotificationCommandRunner;
use TPanel\Notifications\NotificationEvent;
use TPanel\Notifications\NotificationEventDraft;
use TPanel\Notifications\NotificationValidationException;
use TPanel\Repositories\NotificationEventRepository;

final class NotificationService
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly NotificationEventRepository $events,
        private readonly array $config,
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
        private readonly NotificationCommandRunner $runner = new LocalNotificationCommandRunner(),
    ) {
    }

    public function prepare(NotificationEventDraft $draft): NotificationEvent
    {
        $sanitizedDraft = $this->sanitizeAndValidate($draft);
        $enabled = ($this->config['enabled'] ?? false) === true;

        return $this->events->create(
            $sanitizedDraft,
            $enabled ? 'PENDING' : 'SKIPPED',
            null,
            $enabled ? null : 'NotiCLI integration is disabled.'
        );
    }

    public function send(NotificationEventDraft $draft): NotificationEvent
    {
        return $this->dispatch($this->prepare($draft));
    }

    public function dispatch(NotificationEvent $event): NotificationEvent
    {
        if ($event->deliveryStatus() !== 'PENDING') {
            return $event;
        }

        if (($this->config['enabled'] ?? false) !== true) {
            return $this->events->updateDeliveryResult($event, 'SKIPPED', null, 'NotiCLI integration is disabled.');
        }

        $binaryPath = (string) ($this->config['binaryPath'] ?? '');

        if ($binaryPath === '') {
            return $this->events->updateDeliveryResult($event, 'FAILED', null, 'NotiCLI binary path is not configured.');
        }

        $result = $this->runner->run(
            $binaryPath,
            $this->argumentsFor($event),
            max(1, (int) ($this->config['timeoutSeconds'] ?? 10))
        );

        if ($result->timedOut) {
            return $this->events->updateDeliveryResult($event, 'FAILED', $result->exitCode, 'NotiCLI execution timed out.');
        }

        if ($result->exitCode === 0) {
            return $this->events->updateDeliveryResult($event, 'SENT', 0, null);
        }

        return $this->events->updateDeliveryResult(
            $event,
            'FAILED',
            $result->exitCode,
            $this->diagnosticFrom($result->stderr, $result->stdout)
        );
    }

    private function sanitizeAndValidate(NotificationEventDraft $draft): NotificationEventDraft
    {
        $sender = trim($draft->sender);
        $category = trim($draft->category);
        $priority = trim($draft->priority);
        $title = $this->sanitizeRequiredText($draft->title, 'Notification title');
        $message = $this->sanitizeRequiredText($draft->message, 'Notification message');

        if ($sender === '' || strlen($sender) > 20) {
            throw new NotificationValidationException('Notification sender is required and must be at most 20 characters.');
        }

        if (!in_array($category, $this->stringList('categories'), true)) {
            throw new NotificationValidationException(sprintf('Notification category "%s" is not allowed.', $category));
        }

        if (!in_array($priority, $this->stringList('priorities'), true)) {
            throw new NotificationValidationException(sprintf('Notification priority "%s" is not allowed.', $priority));
        }

        return new NotificationEventDraft(
            idAlert: $draft->idAlert,
            sender: $sender,
            category: $category,
            priority: $priority,
            title: $title,
            message: $message,
        );
    }

    /**
     * @return list<string>
     */
    private function argumentsFor(NotificationEvent $event): array
    {
        $arguments = [(string) ($this->config['sendCommand'] ?? 'send')];

        if (($this->config['useConfigOverride'] ?? false) === true && is_string($this->config['configPath'] ?? null)) {
            $arguments[] = '--config';
            $arguments[] = $this->config['configPath'];
        }

        return array_merge($arguments, [
            '--sender',
            $event->sender(),
            '--category',
            $event->category(),
            '--priority',
            $event->priority(),
            '--title',
            $event->title(),
            '--message',
            $event->message(),
        ]);
    }

    private function diagnosticFrom(?string $stderr, ?string $stdout): ?string
    {
        $diagnostic = trim((string) ($stderr ?: $stdout));

        if ($diagnostic === '') {
            return 'NotiCLI execution failed without diagnostic output.';
        }

        return $this->sanitizer->sanitizeText($diagnostic);
    }

    private function sanitizeRequiredText(string $text, string $label): string
    {
        $sanitized = $this->sanitizer->sanitizeText($text) ?? '';

        if ($sanitized === '') {
            throw new NotificationValidationException(sprintf('%s is required.', $label));
        }

        return $sanitized;
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $value = $this->config[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
    }
}
