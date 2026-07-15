<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPanel\Support\Application;

final class ApplicationPostRoundtripTest extends TestCase
{
    public function testAdministratorExecutesAuthorizedActionAndReceivesAuditedResult(): void
    {
        $html = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-admin-status',
                'actionKey' => 'service.status',
                'parameters' => ['serviceName' => 'apache2.service'],
                'confirmationAccepted' => '1',
            ],
            server: ['REMOTE_USER' => 'admin.local', 'TPANEL_ROLE' => 'ADMINISTRATOR']
        );

        self::assertStringContainsString('data-result-status="SUCCESS"', $html);
        self::assertStringContainsString('data-request-id="req-admin-status"', $html);
        self::assertStringContainsString('apache2.service', $html);
        self::assertStringContainsString('auditoria #1', $html);
        self::assertStringContainsString('ADMINISTRATOR', $html);
    }

    public function testMonitorDoesNotReceiveActionControlsAndPostIsDeniedBeforeExecution(): void
    {
        $getHtml = $this->application()->handle(
            method: 'GET',
            post: [],
            server: ['REMOTE_USER' => 'monitor.local', 'TPANEL_ROLE' => 'MONITOR']
        );

        self::assertStringContainsString('MONITOR', $getHtml);
        self::assertStringContainsString('Sem acoes permitidas', $getHtml);
        self::assertStringNotContainsString('Service status</button>', $getHtml);

        $postHtml = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-monitor-denied',
                'actionKey' => 'service.status',
                'parameters' => ['serviceName' => 'apache2.service'],
                'confirmationAccepted' => '1',
            ],
            server: ['REMOTE_USER' => 'monitor.local', 'TPANEL_ROLE' => 'MONITOR']
        );

        self::assertStringContainsString('data-result-status="DENIED"', $postHtml);
        self::assertStringContainsString('Only Administrators can execute administrative actions.', $postHtml);
        self::assertStringContainsString('auditoria #1', $postHtml);
    }

    public function testInvalidAdministrativeParameterIsRejectedBeforeExecution(): void
    {
        $html = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-invalid-param',
                'actionKey' => 'service.status',
                'parameters' => ['serviceName' => 'apache2.service;rm'],
                'confirmationAccepted' => '1',
            ],
            server: ['REMOTE_USER' => 'admin.local', 'TPANEL_ROLE' => 'ADMINISTRATOR']
        );

        self::assertStringContainsString('data-result-status="DENIED"', $html);
        self::assertStringContainsString('Parameter &quot;serviceName&quot; does not match the allowed pattern.', $html);
        self::assertStringContainsString('auditoria #1', $html);
    }

    public function testMonitorAcknowledgesAlertAndCommentsEventWithoutAdministrativeCommand(): void
    {
        $ackHtml = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-ack',
                'alertId' => '41',
                'acknowledgementNote' => 'checked token=abc123',
            ],
            server: ['REMOTE_USER' => 'monitor.local', 'TPANEL_ROLE' => 'MONITOR']
        );

        self::assertStringContainsString('data-result-status="SUCCESS"', $ackHtml);
        self::assertStringContainsString('Alert acknowledged with status ACKNOWLEDGED.', $ackHtml);
        self::assertStringContainsString('auditoria #1', $ackHtml);

        $commentHtml = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-comment',
                'targetType' => 'ALERT',
                'targetId' => '41',
                'commentText' => 'investigated password=plain',
            ],
            server: ['REMOTE_USER' => 'monitor.local', 'TPANEL_ROLE' => 'MONITOR']
        );

        self::assertStringContainsString('data-result-status="SUCCESS"', $commentHtml);
        self::assertStringContainsString('Comment #1 registered.', $commentHtml);
        self::assertStringContainsString('auditoria #1', $commentHtml);
    }

    public function testDashboardPayloadKeepsUiContractFieldsVisibleOnRoundtrip(): void
    {
        $html = $this->application()->handle(
            method: 'POST',
            post: [
                'requestId' => 'req-contract',
                'actionKey' => 'service.status',
                'parameters' => ['serviceName' => 'mysql.service'],
                'confirmationAccepted' => '1',
            ],
            server: ['REMOTE_USER' => 'admin.local', 'TPANEL_ROLE' => 'ADMINISTRATOR']
        );

        foreach ([
            'Saude geral',
            'Uptime',
            'CPU',
            'Memoria',
            'RAID',
            'Docker',
            'Monitoramento detalhado',
            'data-result-status="SUCCESS"',
            'requestId req-contract',
        ] as $expected) {
            self::assertStringContainsString($expected, $html);
        }
    }

    private function application(): Application
    {
        return new Application(commandCatalog: [
            'commands' => [
                'service.status' => [
                    'enabled' => true,
                    'targetType' => 'SERVICE',
                    'displayName' => 'Service status',
                    'commandKey' => 'printf-service',
                    'executablePath' => '/usr/bin/printf',
                    'timeoutSeconds' => 5,
                    'requiresConfirmation' => false,
                    'requiresAdministrator' => true,
                    'allowedParametersSchema' => [
                        'serviceName' => [
                            'type' => 'string',
                            'pattern' => '^[a-zA-Z0-9@_.-]+$',
                            'maxLength' => 120,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
