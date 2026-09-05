<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\NameResolver;
use Goat\Support\StubRenderer;

final class ControllerGenerator
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
        $class = "{$model}Controller";
        $namespace = $this->namespace();
        $path = $this->path($class);
        $variable = NameResolver::variableName($model);

        $contents = $this->stubs->render('controller', [
            'namespace' => $namespace,
            'class' => $class,
            'model' => $model,
            'variable' => $variable,
            'modelNamespace' => $this->modelNamespace(),
            'requestNamespace' => $this->requestNamespace(),
            'resourceNamespace' => $this->resourceNamespace(),
            'serviceNamespace' => $this->serviceNamespace(),
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.controller', 'App\\Http\\Controllers');
    }

    private function modelNamespace(): string
    {
        return GoatConfig::string('goat.namespaces.model', 'App\\Models');
    }

    private function requestNamespace(): string
    {
        return GoatConfig::string('goat.namespaces.request', 'App\\Http\\Requests');
    }

    private function resourceNamespace(): string
    {
        return GoatConfig::string('goat.namespaces.resource', 'App\\Http\\Resources');
    }

    private function serviceNamespace(): string
    {
        return GoatConfig::string('goat.namespaces.service', 'App\\Services');
    }

    private function path(string $class): string
    {
        $base = $this->resolvePath('controller', 'app/Http/Controllers');
        return rtrim($base, '/') . '/' . $class . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'controller' && function_exists('app_path')) {
            try { return \app_path('Http/Controllers'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }
}
