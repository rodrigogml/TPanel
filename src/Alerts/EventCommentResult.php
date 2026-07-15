<?php

declare(strict_types=1);

namespace TPanel\Alerts;

final class EventCommentResult
{
    public function __construct(
        public readonly int $commentId,
        public readonly int $auditRecordId,
    ) {
    }
}
