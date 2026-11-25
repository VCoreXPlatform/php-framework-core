<?php

declare(strict_types=1);

namespace Core;

use Core\DB\Database;
use Core\DB\Query;
use Core\DB\Table;

abstract class Model
{
    protected const string TABLE = '';
    protected const array FILLABLE = [];

    private readonly Table $table;

    public function __construct(protected readonly Database $database)
    {
        $this->table = $database->table(static::TABLE, static::FILLABLE);
    }

    public function all(): array
    {
        return $this->query()->all();
    }

    public function find(int $id): ?array
    {
        return $this->table->find($id);
    }

    public function create(array $data): array
    {
        return $this->table->create($data);
    }

    public function where(string $column, string|int|float|bool|null $value): Query
    {
        return $this->query()->where($column, $value);
    }

    public function update(int $id, array $data): ?array
    {
        return $this->table->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->table->delete($id);
    }

    public function paginate(int $page = 1, int $perPage = 20): array
    {
        return $this->query()->paginate($page, $perPage);
    }

    public function query(): Query
    {
        return $this->table->query();
    }
}
