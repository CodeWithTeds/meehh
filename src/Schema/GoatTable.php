<?php

declare(strict_types=1);

namespace Goat\Schema;

use Goat\Support\NameResolver;

final class GoatTable
{
    /** @var GoatColumn[] */
    public array $columns = [];

    /** @var GoatRelationship[] */
    public array $relationships = [];

    public bool $hasTimestamps = false;
    public bool $hasSoftDeletes = false;

    public function __construct(
        public string $name,
        public string $modelName,
    ) {}

    public function addColumn(GoatColumn $column): void
    {
        // avoid duplicates (e.g., timestamps adds created_at/updated_at)
        foreach ($this->columns as $existing) {
            if ($existing->name === $column->name) {
                return;
            }
        }
        $this->columns[] = $column;
    }

    public function addRelationship(GoatRelationship $relationship): void
    {
        $this->relationships[] = $relationship;
    }

    public function getColumn(string $name): ?GoatColumn
    {
        foreach ($this->columns as $col) {
            if ($col->name === $name) {
                return $col;
            }
        }
        return null;
    }

    /** @return GoatColumn[] */
    public function fillableColumns(): array
    {
        return array_values(array_filter($this->columns, fn (GoatColumn $c) => $c->isFillable()));
    }

    /** @return GoatColumn[] */
    public function allColumns(): array
    {
        return $this->columns;
    }

    public static function fromName(string $tableName): self
    {
        return new self(
            name: $tableName,
            modelName: NameResolver::modelName($tableName),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'modelName' => $this->modelName,
            'columns' => array_map(fn (GoatColumn $c) => $c->toArray(), $this->columns),
            'relationships' => array_map(fn (GoatRelationship $r) => $r->toArray(), $this->relationships),
            'hasTimestamps' => $this->hasTimestamps,
            'hasSoftDeletes' => $this->hasSoftDeletes,
        ];
    }
}
