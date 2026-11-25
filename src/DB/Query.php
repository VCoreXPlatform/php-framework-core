<?php

declare(strict_types=1);

namespace Core\DB;

use InvalidArgumentException;
use RedBeanPHP\OODB;
use RedBeanPHP\OODBBean;

final class Query
{
    private array $conditions = [];
    private array $bindings = [];
    private array $order = [];

    public function __construct(
        private readonly OODB $orm,
        private readonly string $table,
        private readonly array $columns,
    ) {
        Identifier::validate($table);
    }

    public function where(string $column, string|int|float|bool|null $value): self
    {
        $column = $this->column($column);
        $query = clone $this;
        $query->conditions[] = $column . ($value === null ? ' IS NULL' : ' = ?');
        if ($value !== null) {
            $query->bindings[] = is_bool($value) ? (int) $value : $value;
        }
        return $query;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid sort direction');
        }
        $query = clone $this;
        $query->order[] = $this->column($column) . ' ' . $direction;
        return $query;
    }

    public function all(): array
    {
        return $this->rows();
    }

    public function paginate(int $page = 1, int $perPage = 20): array
    {
        if ($page < 1 || $page > 1_000_000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid pagination bounds');
        }
        $offset = ($page - 1) * $perPage;
        return [
            'total' => (int) $this->orm->count($this->table, $this->filter(), $this->bindings),
            'data' => $this->rows(" LIMIT {$perPage} OFFSET {$offset}"),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    private function rows(string $window = ''): array
    {
        $order = implode(', ', [...$this->order, 'id ASC']);
        $beans = $this->orm->find(
            $this->table,
            [],
            $this->filter() . " ORDER BY {$order}" . $window,
            $this->bindings,
        );
        return array_values(array_map(static fn (OODBBean $bean): array => $bean->export(), $beans));
    }

    private function filter(): string
    {
        return $this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', $this->conditions);
    }

    private function column(string $column): string
    {
        if (!in_array($column, $this->columns, true)) {
            throw new InvalidArgumentException('Invalid query column');
        }
        return '`' . Identifier::validate($column) . '`';
    }
}
