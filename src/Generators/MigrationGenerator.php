<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatColumn;
use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\StubRenderer;

final class MigrationGenerator
{
    public function __construct(
        private readonly StubRenderer $stubs,
    ) {}

    /**
     * @return array{path:string, contents:string}
     */
    public function generate(GoatTable $table): array
    {
        $path = $this->path($table);
        $columns = $this->buildColumns($table);

        $contents = $this->stubs->render('migration', [
            'table' => $table->name,
            'columns' => $columns,
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function path(GoatTable $table): string
    {
        $base = $this->resolvePath('migration', 'database/migrations');
        $timestamp = date('Y_m_d_His');
        return rtrim($base, '/') . '/' . $timestamp . '_create_' . $table->name . '_table.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'migration' && function_exists('database_path')) {
            try { return data\base_path('migrations'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }

    private function buildColumns(GoatTable $table): string
    {
        $lines = [];
        foreach ($table->columns as $col) {
            $line = $this->columnToBlueprint($col, $table);
            if ($line !== null) {
                $lines[] = '            ' . $line;
            }
        }
        if ($table->hasTimestamps) {
            $hasCreated = $table->getColumn('created_at') !== null;
            $hasUpdated = $table->getColumn('updated_at') !== null;
            if (! $hasCreated || ! $hasUpdated) {
                $hasLine = false;
                foreach ($lines as $l) {
                    if (str_contains($l, '->timestamps()') || str_contains($l, 'timestamps()')) {
                        $hasLine = true;
                        break;
                    }
                }
                if (! $hasLine) {
                    $lines[] = '            $table->timestamps();';
                }
            }
        }
        if ($table->hasSoftDeletes) {
            $hasDeleted = $table->getColumn('deleted_at') !== null;
            $hasLine = false;
            foreach ($lines as $l) {
                if (str_contains($l, 'softDeletes')) {
                    $hasLine = true;
                    break;
                }
            }
            if (! $hasDeleted && ! $hasLine) {
                $lines[] = '            $table->softDeletes();';
            }
        }
        return implode("\n", $lines);
    }

    private function columnToBlueprint(GoatColumn $col, GoatTable $table): ?string
    {
        if ($col->primary && $col->name === 'id') {
            if ($col->type === 'bigInteger' || $col->type === 'unsignedBigInteger' || $col->type === 'foreignId') {
                return "\$table->id();";
            }
            if ($col->type === 'ulid') {
                return "\$table->ulid('id')->primary();";
            }
            if ($col->type === 'uuid') {
                return "\$table->uuid('id')->primary();";
            }
            return "\$table->id();";
        }
        if ($col->isSoftDelete || ($col->name === 'deleted_at' && $table->hasSoftDeletes)) {
            return "\$table->softDeletes();";
        }
        if ($col->isTimestamps) {
            return null;
        }
        if (in_array($col->name, ['created_at', 'updated_at'], true) && $table->hasTimestamps) {
            return null;
        }
        $method = $this->typeToMethod($col);
        $args = $this->buildArgs($col, $method);
        $chain = '';
        if ($col->nullable) {
            $chain .= '->nullable()';
        }
        if ($col->hasDefault) {
            $def = $this->formatDefault($col->default);
            $chain .= "->default({$def})";
        }
        if ($col->unique) {
            $chain .= '->unique()';
        }
        if ($col->indexed && ! $col->unique) {
            $chain .= '->index()';
        }
        if ($col->unsigned && ! in_array($method, ['foreignId', 'id'], true)) {
            if (in_array($col->type, ['bigInteger', 'integer', 'smallInteger'], true)) {
                $chain .= '->unsigned()';
            }
        }
        if ($col->isForeignKey && $method === 'foreignId') {
            $chain .= '->constrained(';
            if ($col->foreignTable !== null) {
                $inferred = \Goat\Support\NameResolver::relatedTableFromForeignKey($col->name);
                if ($col->foreignTable !== $inferred) {
                    $chain .= "'{$col->foreignTable}'";
                    if ($col->foreignColumn !== null && $col->foreignColumn !== 'id') {
                        $chain .= ", '{$col->foreignColumn}'";
                    }
                }
            }
            $chain .= ')';
        } elseif ($col->isForeignKey && $method !== 'foreignId') {
            if ($col->foreignTable) {
                $chain .= "->constrained('{$col->foreignTable}')";
            }
        }
        return "\$table->{$method}({$args}){$chain};";
    }

    private function typeToMethod(GoatColumn $col): string
    {
        return match ($col->type) {
            'bigInteger' => 'bigInteger',
            'unsignedBigInteger' => 'unsignedBigInteger',
            'foreignId' => 'foreignId',
            'integer', 'smallInteger', 'tinyInteger' => $col->type === 'integer' ? 'integer' : $col->type,
            'string' => 'string',
            'char' => 'char',
            'text' => 'text',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'uuid' => 'uuid',
            'ulid' => 'ulid',
            'json' => 'json',
            'enum' => 'enum',
            'binary' => 'binary',
            default => 'string',
        };
    }

    private function buildArgs(GoatColumn $col, string $method): string
    {
        $args = "'{$col->name}'";
        if (in_array($method, ['string', 'char'], true) && $col->length !== null) {
            $args .= ", {$col->length}";
        } elseif (in_array($method, ['decimal', 'float', 'double'], true)) {
            if ($col->precision !== null) {
                $args .= ", {$col->precision}";
                if ($col->scale !== null) {
                    $args .= ", {$col->scale}";
                }
            }
        } elseif ($method === 'enum' && $col->enumValues !== null) {
            $vals = implode(', ', array_map(fn ($v) => "'{$v}'", $col->enumValues));
            $args .= ", [{$vals}]";
        }
        return $args;
    }

    private function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $escaped = str_replace("'", "\\'", (string) $value);
        return "'{$escaped}'";
    }
}
