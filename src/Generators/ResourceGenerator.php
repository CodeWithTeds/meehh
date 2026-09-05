<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\StubRenderer;

final class ResourceGenerator
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
        $class = "{$model}Resource";
        $namespace = $this->namespace();
        $modelNamespace = $this->modelNamespace();
        $path = $this->path($class);
        $fields = $this->buildFields($table);

        $contents = $this->stubs->render('resource', [
            'namespace' => $namespace,
            'class' => $class,
            'model' => $model,
            'modelNamespace' => $modelNamespace,
            'fields' => $fields,
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.resource', 'App\\Http\\Resources');
    }

    private function modelNamespace(): string
    {
        return GoatConfig::string('goat.namespaces.model', 'App\\Models');
    }

    private function path(string $class): string
    {
        $base = $this->resolvePath('resource', 'app/Http/Resources');
        return rtrim($base, '/') . '/' . $class . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'resource' && function_exists('app_path')) {
            try { return \app_path('Http/Resources'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }

    private function buildFields(GoatTable $table): string
    {
        $lines = [];
        foreach ($table->columns as $col) {
            $lines[] = "            '{$col->name}' => \$this->{$col->name},";
        }
        foreach ($table->relationships as $rel) {
            $method = $rel->methodName ?? \Goat\Support\NameResolver::relationMethodForForeignKey($rel->foreignKey);
            $lines[] = "            '{$method}' => \$this->whenLoaded('{$method}'),";
        }
        if (empty($lines)) {
            return "            'id' => \$this->id,";
        }
        return implode("\n", $lines);
    }
}
