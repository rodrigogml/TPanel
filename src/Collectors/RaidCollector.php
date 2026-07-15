<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class RaidCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): RaidSnapshot
    {
        $content = $this->dataSource->readFile('/proc/mdstat') ?? '';
        $arrays = $this->parseMdstat($content);

        return new RaidSnapshot(
            available: $arrays !== [],
            arrays: $arrays,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{name: string, state: string, syncPercent: float|null, degradedDisks: int}>
     */
    private function parseMdstat(string $content): array
    {
        $arrays = [];
        $current = null;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match('/^(md[0-9]+)\s*:\s*(active|inactive)\s+(.+)$/', trim($line), $matches) === 1) {
                if ($current !== null) {
                    $arrays[] = $current;
                }

                $current = [
                    'name' => $matches[1],
                    'state' => strtoupper($matches[2]),
                    'syncPercent' => null,
                    'degradedDisks' => 0,
                ];
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/\[(U|_)+\]/', $line, $matches) === 1) {
                $current['degradedDisks'] = substr_count($matches[0], '_');
                $current['state'] = $current['degradedDisks'] > 0 ? 'DEGRADED' : $current['state'];
            }

            if (preg_match('/(?:recovery|resync|reshape)\s*=\s*([0-9.]+)%/', $line, $matches) === 1) {
                $current['syncPercent'] = (float) $matches[1];
                $current['state'] = 'SYNCING';
            }
        }

        if ($current !== null) {
            $arrays[] = $current;
        }

        return $arrays;
    }
}
