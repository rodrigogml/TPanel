<?php

declare(strict_types=1);

namespace TPanel\Services;

use JsonException;
use TPanel\Collectors\LocalSystemDataSource;
use TPanel\Collectors\SystemDataSource;

final class DiskOverviewService
{
    private const LSBLK_COLUMNS = 'NAME,PATH,TYPE,SIZE,MODEL,SERIAL,TRAN,ROTA,RM,MOUNTPOINTS,FSTYPE,UUID,PARTUUID';
    private const FINDMNT_COLUMNS = 'TARGET,SOURCE,FSTYPE,OPTIONS,SIZE,USED,AVAIL,USE%';

    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $smartctl = $this->detectSmartctl();

        return [
            'collectedAt' => gmdate('c'),
            'smartctl' => [
                'available' => $smartctl['available'],
                'path' => $smartctl['path'],
                'version' => $smartctl['version'],
                'warning' => $smartctl['available']
                    ? null
                    : 'Informações SMART, temperatura, horas ligadas e erros físicos dependem do smartctl instalado. Instale o pacote smartmontools para liberar a coleta completa.',
            ],
            'blockDevices' => $this->blockDevices(),
            'mounts' => $this->mounts(),
            'fstab' => $this->fstabEntries(),
        ];
    }

    /**
     * @return array{available: bool, path: string|null, version: string|null}
     */
    private function detectSmartctl(): array
    {
        foreach (['/usr/sbin/smartctl', '/usr/local/sbin/smartctl', '/usr/bin/smartctl'] as $path) {
            $output = $this->dataSource->runCommand($path, '--version');

            if ($output === null || trim($output) === '') {
                continue;
            }

            $lines = preg_split('/\R/', trim($output)) ?: [];

            return [
                'available' => true,
                'path' => $path,
                'version' => trim((string) ($lines[0] ?? 'smartctl detectado')),
            ];
        }

        return [
            'available' => false,
            'path' => null,
            'version' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockDevices(): array
    {
        $output = $this->dataSource->runCommand('/bin/lsblk', '-J', '-b', '-o', self::LSBLK_COLUMNS)
            ?? $this->dataSource->runCommand('/usr/bin/lsblk', '-J', '-b', '-o', self::LSBLK_COLUMNS);
        $payload = $this->decodeJsonObject($output);
        $devices = $payload['blockdevices'] ?? [];

        if (!is_array($devices)) {
            return [];
        }

        return $this->flattenBlockDevices($devices);
    }

    /**
     * @param list<mixed> $devices
     * @return list<array<string, mixed>>
     */
    private function flattenBlockDevices(array $devices, string $parent = '', int $depth = 0): array
    {
        $rows = [];

        foreach ($devices as $device) {
            if (!is_array($device)) {
                continue;
            }

            $children = $device['children'] ?? [];
            unset($device['children']);

            $path = $this->stringValue($device['path'] ?? null);
            $device['parent'] = $parent;
            $device['depth'] = $depth;
            $device['mountpoints'] = $this->normalizeMountpoints($device['mountpoints'] ?? null);
            $rows[] = $device;

            if (is_array($children)) {
                array_push($rows, ...$this->flattenBlockDevices($children, $path, $depth + 1));
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mounts(): array
    {
        $output = $this->dataSource->runCommand('/bin/findmnt', '-J', '-b', '-o', self::FINDMNT_COLUMNS)
            ?? $this->dataSource->runCommand('/usr/bin/findmnt', '-J', '-b', '-o', self::FINDMNT_COLUMNS);
        $payload = $this->decodeJsonObject($output);
        $filesystems = $payload['filesystems'] ?? [];

        if (!is_array($filesystems)) {
            return [];
        }

        return $this->flattenMounts($filesystems);
    }

    /**
     * @param list<mixed> $mounts
     * @return list<array<string, mixed>>
     */
    private function flattenMounts(array $mounts): array
    {
        $rows = [];

        foreach ($mounts as $mount) {
            if (!is_array($mount)) {
                continue;
            }

            $children = $mount['children'] ?? [];
            unset($mount['children']);
            $rows[] = $mount;

            if (is_array($children)) {
                array_push($rows, ...$this->flattenMounts($children));
            }
        }

        return $rows;
    }

    /**
     * @return list<array{source: string, target: string, fstype: string, options: string, dump: string, pass: string}>
     */
    private function fstabEntries(): array
    {
        $content = $this->dataSource->readFile('/etc/fstab') ?? '';
        $entries = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];

            if (count($parts) < 4) {
                continue;
            }

            $entries[] = [
                'source' => $parts[0],
                'target' => $parts[1],
                'fstype' => $parts[2],
                'options' => $parts[3],
                'dump' => $parts[4] ?? '0',
                'pass' => $parts[5] ?? '0',
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(?string $output): array
    {
        if ($output === null || trim($output) === '') {
            return [];
        }

        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return list<string>
     */
    private function normalizeMountpoints(mixed $mountpoints): array
    {
        if (is_array($mountpoints)) {
            return array_values(array_filter(array_map(
                fn (mixed $value): string => $this->stringValue($value),
                $mountpoints,
            ), fn (string $value): bool => $value !== ''));
        }

        $value = $this->stringValue($mountpoints);

        return $value === '' ? [] : [$value];
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
