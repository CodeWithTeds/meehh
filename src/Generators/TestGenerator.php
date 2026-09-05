<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatColumn;
use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\StubRenderer;
use Illuminate\Support\Str;

final class TestGenerator
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
        $class = "{$model}Test";
        $namespace = $this->namespace();
        $path = $this->path($class);
        $factoryPayload = $this->buildFactoryPayload($table);
        $assertPayload = $this->buildAssertPayload($table);

        $contents = $this->stubs->render('test', [
            'namespace' => $namespace,
            'class' => $class,
            'model' => $model,
            'table' => $table->name,
            'singular' => Str::singular($table->name),
            'modelNamespace' => GoatConfig::string('goat.namespaces.model', 'App\\Models'),
            'factoryPayload' => $factoryPayload,
            'assertPayload' => $assertPayload,
        ]);

        return ['path' => $path, 'contents' => $contents];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.test', 'Tests\\Feature');
    }

    private function path(string $class): string
    {
        $base = $this->resolvePath('test', 'tests/Feature');
        return rtrim($base, '/') . '/' . $class . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }

    private function buildFactoryPayload(GoatTable $table): string
    {
        $lines = [];
        foreach ($table->fillableColumns() as $col) {
            $fake = $this->fakeValue($col);
            $lines[] = "            '{$col->name}' => {$fake},";
        }
        if (empty($lines)) {
            return "            // no payload";
        }
        return implode("\n", $lines);
    }

    private function buildAssertPayload(GoatTable $table): string
    {
        $cols = $table->fillableColumns();
        if (empty($cols)) {
            return "            // ";
        }
        $first = $cols[0];
        return "            '{$first->name}' => \$payload['{$first->name}'],";
    }

    private function fakeValue(GoatColumn $col): string
    {
        $nameLower = strtolower($col->name);
        if (str_contains($nameLower, 'email') && $col->type === 'string') {
            return 'fake()->safeEmail()';
        }
        if (str_contains($nameLower, 'name') && $col->type === 'string') {
            return 'fake()->name()';
        }
        if ($col->name === 'title' || $col->name === 'name') {
            return 'fake()->sentence(3)';
        }
        if (str_contains($nameLower, 'price') || $col->type === 'decimal') {
            return 'fake()->randomFloat(2, 1, 999)';
        }
        if ($col->type === 'integer' || $col->type === 'bigInteger' || $col->type === 'foreignId') {
            if ($col->isForeignKey) {
                return '1';
            }
            return 'fake()->randomNumber()';
        }
        if ($col->type === 'boolean') {
            return 'fake()->boolean()';
        }
        if ($col->type === 'text') {
            return 'fake()->paragraph()';
        }
        if ($col->type === 'date' || $col->type === 'datetime' || $col->type === 'timestamp') {
            return 'now()->toDateTimeString()';
        }
        if ($col->type === 'json') {
            return '[]';
        }
        if ($col->type === 'enum' && $col->enumValues !== null) {
            $first = $col->enumValues[0] ?? 'draft';
            return "'{$first}'";
        }
        if ($col->type === 'uuid') {
            return 'fake()->uuid()';
        }
        return 'fake()->word()';
    }
}
