<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\NameResolver;
use Goat\Support\StubRenderer;

final class PolicyGenerator
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
        $class = "{$model}Policy";
        $namespace = $this->namespace();
        $path = $this->path($class);
        $variable = NameResolver::variableName($model);

        $contents = $this->stubs->render('policy', [
            'namespace' => $namespace,
            'class' => $class,
            'model' => $model,
            'variable' => $variable,
            'modelNamespace' => GoatConfig::string('goat.namespaces.model', 'App\\Models'),
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.policy', 'App\\Policies');
    }

    private function path(string $class): string
    {
        $base = $this->resolvePath('policy', 'app/Policies');
        return rtrim($base, '/') . '/' . $class . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'policy' && function_exists('app_path')) {
            try { return \app_path('Policies'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }
}
