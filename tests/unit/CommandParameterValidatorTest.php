<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Command\AuthorizedCommandExecutor;
use TPanel\Command\CommandParameterValidationResult;
use TPanel\Command\CommandParameterValidator;

final class CommandParameterValidatorTest extends TestCase
{
    public function testAcceptsValidStringParameterFromCatalog(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('service.status'),
            ['serviceName' => 'apache2.service']
        );

        self::assertTrue($result->valid());
        self::assertSame(CommandParameterValidationResult::VALID, $result->reasonCode());
        self::assertSame(['serviceName' => 'apache2.service'], $result->validatedParameters());
    }

    public function testAcceptsValidEnumParametersFromCatalog(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('notification.test'),
            [
                'category' => 'service',
                'priority' => 'HIGH',
            ]
        );

        self::assertTrue($result->valid());
        self::assertSame([
            'category' => 'service',
            'priority' => 'HIGH',
        ], $result->validatedParameters());
    }

    public function testRejectsMissingRequiredParameter(): void
    {
        $result = $this->validator()->validate($this->catalogEntry('service.status'), []);

        self::assertFalse($result->valid());
        self::assertSame(CommandParameterValidationResult::PARAMETER_INVALID, $result->reasonCode());
        self::assertSame('Parameter "serviceName" is required.', $result->message());
    }

    public function testRejectsExtraParameterBeforeExecution(): void
    {
        $executor = new AuthorizedCommandExecutor();

        $result = $executor->validateParameters(
            $this->catalogEntry('service.status'),
            [
                'serviceName' => 'apache2.service',
                'shell' => 'id',
            ]
        );

        self::assertFalse($result->valid());
        self::assertSame(CommandParameterValidationResult::PARAMETER_INVALID, $result->reasonCode());
        self::assertSame([], $result->validatedParameters());
    }

    public function testRejectsMalformedStringParameter(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('service.status'),
            ['serviceName' => 'apache2.service;systemctl restart ssh']
        );

        self::assertFalse($result->valid());
        self::assertStringContainsString('allowed pattern', $result->message());
    }

    public function testRejectsInvalidEnumValue(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('monitoring.collect.once'),
            ['metricCategory' => 'ALL; rm -rf /']
        );

        self::assertFalse($result->valid());
        self::assertSame('Parameter "metricCategory" is not an allowed value.', $result->message());
    }

    public function testRejectsStringLongerThanCatalogMaximum(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('docker.container.status'),
            ['containerName' => str_repeat('a', 129)]
        );

        self::assertFalse($result->valid());
        self::assertSame('Parameter "containerName" exceeds maximum length.', $result->message());
    }

    public function testRejectsTimerNameWithoutTimerSuffix(): void
    {
        $result = $this->validator()->validate(
            $this->catalogEntry('schedule.timer.status'),
            ['timerName' => 'backup.service']
        );

        self::assertFalse($result->valid());
        self::assertStringContainsString('allowed pattern', $result->message());
    }

    public function testRejectsMalformedCatalogSchema(): void
    {
        $result = $this->validator()->validate(['enabled' => true], []);

        self::assertFalse($result->valid());
        self::assertSame('Command catalog entry has no parameter schema.', $result->message());
    }

    private function validator(): CommandParameterValidator
    {
        return new CommandParameterValidator();
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogEntry(string $actionKey): array
    {
        $catalog = require __DIR__ . '/../../config/commands.php.model';

        self::assertArrayHasKey($actionKey, $catalog['commands']);

        return $catalog['commands'][$actionKey];
    }
}
