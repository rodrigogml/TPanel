<?php

declare(strict_types=1);

namespace TPanel\Support;

use InvalidArgumentException;
use PDO;

final class DatabaseConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config
    ) {
    }

    public static function fromConfigFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Database configuration file not found: %s', $path));
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('Database configuration file must return an array: %s', $path));
        }

        return new self($config);
    }

    public function create(): PDO
    {
        if (($this->config['driver'] ?? null) !== 'mysql') {
            throw new InvalidArgumentException('Only the mysql database driver is supported.');
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');
        $options = is_array($this->config['options'] ?? null) ? $this->config['options'] : [];

        if ($database === '' || $username === '') {
            throw new InvalidArgumentException('Database name and username are required.');
        }

        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => (bool) ($options['persistent'] ?? false),
        ];

        $timeoutSeconds = (int) ($options['timeoutSeconds'] ?? 5);

        if ($timeoutSeconds > 0) {
            $pdoOptions[PDO::ATTR_TIMEOUT] = $timeoutSeconds;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        return new PDO($dsn, $username, $password, $pdoOptions);
    }
}
