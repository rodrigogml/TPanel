<?php

declare(strict_types=1);

namespace TPanel\Repositories;

final class RepositoryHealthCheck
{
    public function isReady(): bool
    {
        return true;
    }
}
