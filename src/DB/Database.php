<?php

declare(strict_types=1);

namespace Core\DB;

use Closure;
use Core\Config\Config;
use InvalidArgumentException;
use LogicException;
use PDO;
use Pdo\Mysql;
use RedBeanPHP\Facade;
use RedBeanPHP\OODB;
use Throwable;

final readonly class Database
{
    private OODB $orm;

    private function __construct(private PDO $pdo, bool $frozen)
    {
        $this->orm = Facade::createToolbox($pdo, null, null, $frozen)->getRedBean();
    }

    public static function fromConfig(Config $config): self
    {
        $host = $config->get('database.host');
        $name = $config->get('database.database');
        $port = filter_var($config->get('database.port'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if (!preg_match('/\A[a-z0-9.:\[\]-]+\z/i', $host) || !preg_match('/\A[a-z0-9_-]+\z/i', $name) || !$port) {
            throw new InvalidArgumentException('Invalid database settings');
        }
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $config->get('database.username'),
            $config->get('database.password'),
            [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                Mysql::ATTR_MULTI_STATEMENTS => false,
                Mysql::ATTR_LOCAL_INFILE => false,
            ],
        );
        return new self($pdo, $config->get('app.env') === 'production' || $config->get('database.freeze'));
    }

    public function migrations(array $directories): Migrations
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Migrations are available only from the CLI');
        }
        return new Migrations($this->pdo, $directories);
    }

    public function synchronized(string $key, Closure $callback): mixed
    {
        $name = hash('sha256', $this->pdo->query('SELECT DATABASE()')->fetchColumn() . ':' . $key);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$name]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new LogicException('Could not acquire the database lock');
        }
        try {
            return $callback();
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$name]);
        }
    }

    public function isFrozen(): bool
    {
        return $this->orm->isFrozen();
    }

    public function table(string $name, array $fillable): Table
    {
        return new Table($this->orm, $name, $fillable);
    }

    public function transaction(Closure $callback): mixed
    {
        if (!$this->orm->isFrozen() || $this->pdo->inTransaction()) {
            throw new LogicException('Transactions require frozen schema and cannot be nested');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
