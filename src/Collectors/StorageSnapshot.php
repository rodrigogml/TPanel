<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class StorageSnapshot
{
    /**
     * @param list<array{
     *     filesystem: string,
     *     mountPoint: string,
     *     totalBytes: int,
     *     usedBytes: int,
     *     availableBytes: int,
     *     usedPercent: float|null,
     *     totalInodes: int|null,
     *     usedInodes: int|null,
     *     freeInodes: int|null,
     *     inodeUsedPercent: float|null
     * }> $filesystems
     * @param list<array{device: string, readsCompleted: int, writesCompleted: int, sectorsRead: int, sectorsWritten: int}> $diskIo
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $filesystems,
        public readonly array $diskIo,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
