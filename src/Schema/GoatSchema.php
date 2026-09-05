<?php

declare(strict_types=1);

namespace Goat\Schema;

/**
 * Single source of truth for parsed schema.
 * Holds one or more tables (ERD may define multiple).
 */
final class GoatSchema
{
    /** @var GoatTable[] keyed by table name */
    private array $tables = [];

    public function addTable(GoatTable $table): void
    {
        $this->tables[$table->name] = $table;
    }

    public function getTable(string $name): ?GoatTable
    {
        return $this->tables[$name] ?? null;
    }

    /** @return GoatTable[] */
    public function tables(): array
    {
        return array_values($this->tables);
    }

    public function primaryTable(): ?GoatTable
    {
        if (count($this->tables) === 1) {
            return array_values($this->tables)[0];
        }
        // When multiple tables, caller should resolve via name
        return null;
    }

    public function primaryTableFor(string $name): ?GoatTable
    {
        // Try exact table name
        if (isset($this->tables[$name])) {
            return $this->tables[$name];
        }
        // Try by model name
        foreach ($this->tables as $table) {
            if (strcasecmp($table->modelName, $name) === 0) {
                return $table;
            }
        }
        // Fallback: first table
        return $this->primaryTable();
    }

    public function hasTable(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    public function count(): int
    {
        return count($this->tables);
    }

    public function toArray(): array
    {
        return [
            'tables' => array_map(fn (GoatTable $t) => $t->toArray(), $this->tables),
        ];
    }
}
