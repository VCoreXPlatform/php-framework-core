<?php

declare(strict_types=1);

namespace Core\DB;

use PDO;
use RuntimeException;
use Throwable;

final readonly class Migrations
{
    public function __construct(private PDO $pdo, private array $directories)
    {
    }

    public function status(): array
    {
        return $this->compare($this->files());
    }

    private function compare(array $files): array
    {
        $exists = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
             AND table_name = 'framework_migrations'",
        )->fetchColumn();

        $rows = $exists ? $this->pdo->query(
            'SELECT version, checksum, state FROM framework_migrations ORDER BY version',
        )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC) : [];

        $status = [];
        foreach ($files as $name => $sql) {
            $row = $rows[$name] ?? null;
            $status[$name] = match (true) {
                $row === null => 'pending',
                $row['checksum'] !== hash('sha256', $sql) => 'changed',
                default => $row['state'],
            };
        }
        foreach (array_diff_key($rows, $files) as $name => $row) {
            $status[$name] = 'missing';
        }
        return $status;
    }

    public function migrate(): array
    {
        $lockName = $this->pdo->query("SELECT SHA2(CONCAT('framework-migrations:', DATABASE()), 256)")->fetchColumn();
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire the migration lock');
        }

        try {
            return $this->apply();
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    private function apply(): array
    {
        $files = $this->files();
        $status = $this->compare($files);
        foreach ($status as $name => $state) {
            if (!in_array($state, ['pending', 'applied'], true)) {
                throw new RuntimeException("Migration {$name} is {$state}; reconcile it before proceeding");
            }
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS framework_migrations (
                version VARCHAR(190) PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                state VARCHAR(12) NOT NULL,
                applied_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB",
        );
        $record = $this->pdo->prepare("INSERT INTO framework_migrations (version, checksum, state) VALUES (?, ?, 'dirty')");
        $complete = $this->pdo->prepare(
            "UPDATE framework_migrations SET state = 'applied', applied_at = CURRENT_TIMESTAMP WHERE version = ?",
        );
        $applied = [];
        foreach ($status as $name => $state) {
            if ($state !== 'pending') {
                continue;
            }
            $sql = $files[$name];
            $record->execute([$name, hash('sha256', $sql)]);
            try {
                $this->pdo->exec($sql);
                $complete->execute([$name]);
            } catch (Throwable $error) {
                throw new RuntimeException("Migration {$name} failed and is marked dirty: " . $error->getMessage(), 0, $error);
            }
            $applied[] = $name;
        }
        return $applied;
    }

    private function files(): array
    {
        $migrations = [];
        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/*.sql') ?: [] as $file) {
                $name = basename($file, '.sql');
                if (!preg_match('/\A[0-9]{8,14}_[a-z0-9_]{1,120}\z/', $name) || isset($migrations[$name])) {
                    throw new RuntimeException("Invalid or duplicate migration name: {$name}");
                }
                $sql = file_get_contents($file);
                if ($sql === false || trim($sql) === '') {
                    throw new RuntimeException("Empty or unreadable migration: {$name}");
                }
                $migrations[$name] = str_replace("\r\n", "\n", $sql);
            }
        }
        ksort($migrations, SORT_STRING);
        return $migrations;
    }
}
