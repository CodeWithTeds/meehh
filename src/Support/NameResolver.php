<?php

declare(strict_types=1);

namespace Goat\Support;

use Illuminate\Support\Str;

final class NameResolver
{
    public static function modelName(string $tableName): string
    {
        return Str::studly(Str::singular($tableName));
    }

    public static function tableName(string $modelName): string
    {
        // If input already looks like a table (plural snake), keep it; otherwise pluralize snake
        $snake = Str::snake($modelName);
        // If input contains uppercase, it's a Model name -> convert to table
        if (preg_match('/[A-Z]/', $modelName)) {
            return Str::plural(Str::snake($modelName));
        }
        // Assume it's already table-ish; normalize to snake plural
        return Str::plural($snake);
    }

    public static function pluralModelName(string $modelName): string
    {
        return Str::plural($modelName);
    }

    public static function singularTable(string $table): string
    {
        return Str::singular($table);
    }

    public static function relationMethodForForeignKey(string $foreignKey): string
    {
        // category_id => category, product_item_id => productItem
        $base = preg_replace('/_id$/', '', $foreignKey) ?? $foreignKey;
        return Str::camel($base);
    }

    public static function relatedModelFromForeignKey(string $foreignKey): string
    {
        $base = preg_replace('/_id$/', '', $foreignKey) ?? $foreignKey;
        return Str::studly($base);
    }

    public static function relatedTableFromForeignKey(string $foreignKey): string
    {
        $base = preg_replace('/_id$/', '', $foreignKey) ?? $foreignKey;
        return Str::plural(Str::snake($base));
    }

    public static function variableName(string $modelName): string
    {
        return Str::camel($modelName);
    }

    public static function kebab(string $value): string
    {
        return Str::kebab($value);
    }

    public static function ensureValidPhpClassName(string $name): string
    {
        $studly = Str::studly($name);
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $studly)) {
            throw new \InvalidArgumentException("Invalid model/table name [{$name}].");
        }
        return $studly;
    }

    public static function classBasename(string $fqcn): string
    {
        return class_basename($fqcn);
    }
}
