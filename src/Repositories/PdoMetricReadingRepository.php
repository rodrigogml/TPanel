<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;
use TPanel\Monitoring\MetricReading;
use TPanel\Monitoring\MetricReadingDraft;

final class PdoMetricReadingRepository implements MetricReadingRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function append(MetricReadingDraft $draft): MetricReading
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO metricReading (
                idServerHealthSummary,
                metricCategory,
                metricName,
                metricValue,
                unit,
                severity,
                source,
                collectedAt,
                expiresAt
            ) VALUES (
                :idServerHealthSummary,
                :metricCategory,
                :metricName,
                :metricValue,
                :unit,
                :severity,
                :source,
                :collectedAt,
                :expiresAt
            )'
        );

        $statement->execute([
            'idServerHealthSummary' => $draft->idServerHealthSummary,
            'metricCategory' => $draft->metricCategory,
            'metricName' => $draft->metricName,
            'metricValue' => $this->encodeMetricValue($draft->metricValue),
            'unit' => $draft->unit,
            'severity' => $draft->severity,
            'source' => $draft->source,
            'collectedAt' => $draft->collectedAt->format('Y-m-d H:i:s'),
            'expiresAt' => $draft->expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return new MetricReading(
            id: (int) $this->pdo->lastInsertId(),
            idServerHealthSummary: $draft->idServerHealthSummary,
            metricCategory: $draft->metricCategory,
            metricName: $draft->metricName,
            metricValue: $draft->metricValue,
            unit: $draft->unit,
            severity: $draft->severity,
            source: $draft->source,
            collectedAt: $draft->collectedAt,
            expiresAt: $draft->expiresAt,
        );
    }

    public function latest(string $metricCategory, string $metricName, string $source): ?MetricReading
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM metricReading
            WHERE metricCategory = :metricCategory
                AND metricName = :metricName
                AND source = :source
            ORDER BY collectedAt DESC, id DESC
            LIMIT 1'
        );

        $statement->execute([
            'metricCategory' => $metricCategory,
            'metricName' => $metricName,
            'source' => $source,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function history(
        string $metricCategory,
        string $metricName,
        string $source,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 500
    ): array {
        $limit = max(1, min($limit, 5000));
        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM metricReading
                WHERE metricCategory = :metricCategory
                    AND metricName = :metricName
                    AND source = :source
                    AND collectedAt >= :fromDate
                    AND collectedAt <= :toDate
                ORDER BY collectedAt ASC, id ASC
                LIMIT %d',
                $limit
            )
        );

        $statement->execute([
            'metricCategory' => $metricCategory,
            'metricName' => $metricName,
            'source' => $source,
            'fromDate' => $from->format('Y-m-d H:i:s'),
            'toDate' => $to->format('Y-m-d H:i:s'),
        ]);

        $rows = $statement->fetchAll();
        $readings = [];

        foreach ($rows as $row) {
            $readings[] = $this->fromRow($row);
        }

        return $readings;
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM metricReading
            WHERE expiresAt IS NOT NULL
                AND expiresAt <= :nowDate'
        );

        $statement->execute(['nowDate' => $now->format('Y-m-d H:i:s')]);

        return $statement->rowCount();
    }

    private function encodeMetricValue(mixed $metricValue): string
    {
        try {
            return json_encode($metricValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode metric value as JSON.', previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row): MetricReading
    {
        try {
            $metricValue = json_decode((string) $row['metricValue'], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode metric value JSON.', previous: $exception);
        }

        return new MetricReading(
            id: (int) $row['id'],
            idServerHealthSummary: $row['idServerHealthSummary'] === null ? null : (int) $row['idServerHealthSummary'],
            metricCategory: (string) $row['metricCategory'],
            metricName: (string) $row['metricName'],
            metricValue: $metricValue,
            unit: $row['unit'] === null ? null : (string) $row['unit'],
            severity: (string) $row['severity'],
            source: (string) $row['source'],
            collectedAt: new DateTimeImmutable((string) $row['collectedAt']),
            expiresAt: $row['expiresAt'] === null ? null : new DateTimeImmutable((string) $row['expiresAt']),
        );
    }
}
