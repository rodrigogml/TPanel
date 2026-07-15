<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;

interface AuditRecordRepository
{
    public function append(AuditRecordDraft $draft): AuditRecord;
}
