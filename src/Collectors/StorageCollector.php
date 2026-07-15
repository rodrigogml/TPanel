<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class StorageCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): StorageSnapshot
    {
        $filesystems = $this->parseDfBytes($this->dataSource->runCommand('/bin/df', '-P', '-B1') ?? '');
        $inodeData = $this->parseDfInodes($this->dataSource->runCommand('/bin/df', '-P', '-i') ?? '');
        $diskIo = $this->parseDiskstats($this->dataSource->readFile('/proc/diskstats') ?? '');

        foreach ($filesystems as $index => $filesystem) {
            $mountPoint = $filesystem['mountPoint'];
            $filesystems[$index] = array_merge($filesystem, $inodeData[$mountPoint] ?? [
                'totalInodes' => null,
                'usedInodes' => null,
                'freeInodes' => null,
                'inodeUsedPercent' => null,
            ]);
        }

        return new StorageSnapshot(
            available: $filesystems !== [] || $diskIo !== [],
            filesystems: $filesystems,
            diskIo: $diskIo,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{filesystem: string, mountPoint: string, totalBytes: int, usedBytes: int, availableBytes: int, usedPercent: float|null}>
     */
    private function parseDfBytes(string $output): array
    {
        $filesystems = [];
        $lines = array_slice(preg_split('/\R/', trim($output)) ?: [], 1);

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 6) ?: [];

            if (count($parts) < 6) {
                continue;
            }

            $filesystems[] = [
                'filesystem' => $parts[0],
                'totalBytes' => (int) $parts[1],
                'usedBytes' => (int) $parts[2],
                'availableBytes' => (int) $parts[3],
                'usedPercent' => $this->parsePercent($parts[4]),
                'mountPoint' => $parts[5],
            ];
        }

        return $filesystems;
    }

    /**
     * @return array<string, array{totalInodes: int|null, usedInodes: int|null, freeInodes: int|null, inodeUsedPercent: float|null}>
     */
    private function parseDfInodes(string $output): array
    {
        $inodes = [];
        $lines = array_slice(preg_split('/\R/', trim($output)) ?: [], 1);

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 6) ?: [];

            if (count($parts) < 6) {
                continue;
            }

            $inodes[$parts[5]] = [
                'totalInodes' => is_numeric($parts[1]) ? (int) $parts[1] : null,
                'usedInodes' => is_numeric($parts[2]) ? (int) $parts[2] : null,
                'freeInodes' => is_numeric($parts[3]) ? (int) $parts[3] : null,
                'inodeUsedPercent' => $this->parsePercent($parts[4]),
            ];
        }

        return $inodes;
    }

    /**
     * @return list<array{device: string, readsCompleted: int, writesCompleted: int, sectorsRead: int, sectorsWritten: int}>
     */
    private function parseDiskstats(string $content): array
    {
        $stats = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) < 14) {
                continue;
            }

            $device = $parts[2];

            if (preg_match('/^(loop|ram|fd)/', $device)) {
                continue;
            }

            $stats[] = [
                'device' => $device,
                'readsCompleted' => (int) $parts[3],
                'sectorsRead' => (int) $parts[5],
                'writesCompleted' => (int) $parts[7],
                'sectorsWritten' => (int) $parts[9],
            ];
        }

        return $stats;
    }

    private function parsePercent(string $value): ?float
    {
        $normalized = rtrim($value, '%');

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
