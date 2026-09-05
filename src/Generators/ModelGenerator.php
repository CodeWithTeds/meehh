<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\NameResolver;
use Goat\Support\StubRenderer;

final class ModelGenerator
{
    public function __construct(
        private readonly StubRenderer $stubs,
    ) {}

    /**
     * @return array{path:string, contents:string}
     */
    public function generate(GoatTable $table): array
    {
        $model = $table->modelName;
        $namespace = $this->namespace();
        $path = $this->path($model);

        $fillable = $this->buildFillable($table);
        $casts = $this->buildCasts($table);
        $relationships = $this->buildRelationships($table);
        $imports = $this->buildRelationshipImports($table);

        $softDeletesImport = $table->hasSoftDeletes
            ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;"
            : '';

        $softDeletesTrait = $table->hasSoftDeletes
            ? ', SoftDeletes'
            : '';

        $contents = $this->stubs->render('model', [
            'namespace' => $namespace,
            'class' => $model,
            'fillable' => $fillable,
            'casts' => $casts,
            'relationships' => $relationships,
            'relationshipsImports' => $imports !== '' ? "\n" . $imports : '',
            'softDeletesImport' => $softDeletesImport,
            'softDeletesTrait' => $softDeletesTrait,
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.model', 'App\\Models');
    }

    private function path(string $model): string
    {
        $base = $this->resolvePath('model', 'app/Models');
        return rtrim($base, '/') . '/' . $model . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'model' && function_exists('app_path')) {
            try { return \app_path('Models'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }

    private function buildFillable(GoatTable $table): string
    {
        $cols = $table->fillableColumns();
        if (empty($cols)) {
            return "        // no fillable attributes";
        }
        $lines = [];
        foreach ($cols as $col) {
            $lines[] = "        '{$col->name}',";
        }
        return implode("\n", $lines);
    }

    private function buildCasts(GoatTable $table): string
    {
        $casts = [];
        foreach ($table->columns as $col) {
            $cast = $col->castType();
            if ($cast === null) {
                continue;
            }
            if ($col->primary) {
                continue;
            }
            if (in_array($col->type, ['decimal', 'float', 'double', 'boolean', 'json', 'jsonb', 'datetime', 'timestamp', 'date'], true)) {
                $casts[] = "        '{$col->name}' => '{$cast}',";
            }
        }
        if (empty($casts)) {
            return '';
        }
        return "    protected function casts(): array\n    {\n        return [\n" . implode("\n", $casts) . "\n        ];\n    }";
    }

    private function buildRelationships(GoatTable $table): string
    {
        if (empty($table->relationships)) {
            return '';
        }
        $methods = [];
        foreach ($table->relationships as $rel) {
            $relatedModel = $rel->relatedModel;
            $method = $rel->methodName ?? NameResolver::relationMethodForForeignKey($rel->foreignKey);
            $methods[] = "    public function {$method}()\n    {\n        return \$this->belongsTo({$relatedModel}::class, '{$rel->foreignKey}', '{$rel->ownerKey}');\n    }";
        }
        return implode("\n\n", $methods);
    }

    private function buildRelationshipImports(GoatTable $table): string
    {
        if (empty($table->relationships)) {
            return '';
        }
        $models = [];
        $ns = $this->namespace();
        foreach ($table->relationships as $rel) {
            $fqcn = $ns . '\\' . $rel->relatedModel;
            $models[$fqcn] = "use {$fqcn};";
        }
        $currentFqcn = $ns . '\\' . $table->modelName;
        unset($models[$currentFqcn]);
        return implode("\n", array_values($models));
    }
}
