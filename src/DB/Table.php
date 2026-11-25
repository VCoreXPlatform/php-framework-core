<?php

declare(strict_types=1);

namespace Core\DB;

use InvalidArgumentException;
use RedBeanPHP\OODB;
use RedBeanPHP\OODBBean;

final readonly class Table
{
    public function __construct(private OODB $orm, private string $name, private array $fillable)
    {
        Identifier::validate($name);
        foreach ($fillable as $column) {
            Identifier::validate($column);
        }
    }

    public function query(): Query
    {
        return new Query($this->orm, $this->name, ['id', ...$this->fillable]);
    }

    public function find(int $id): ?array
    {
        return $this->record($id)?->export();
    }

    public function create(array $data): array
    {
        return $this->save($this->orm->dispense($this->name), $data);
    }

    public function update(int $id, array $data): ?array
    {
        $bean = $this->record($id);
        return $bean === null ? null : $this->save($bean, $data);
    }

    public function delete(int $id): bool
    {
        $bean = $this->record($id);
        if ($bean === null) {
            return false;
        }
        $this->orm->trash($bean);
        return true;
    }

    private function record(int $id): ?OODBBean
    {
        if ($id < 1) {
            return null;
        }
        $bean = $this->orm->load($this->name, $id);
        return (int) $bean->id === 0 ? null : $bean;
    }

    private function save(OODBBean $bean, array $data): array
    {
        foreach ($this->fillable as $field) {
            if ($field === 'id' || !array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Invalid model field: {$field}");
            }
            $bean->{$field} = $value;
        }
        $this->orm->store($bean);
        return $bean->export();
    }
}
