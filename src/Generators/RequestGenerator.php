<?php

declare(strict_types=1);

namespace Goat\Generators;

use Goat\Schema\GoatTable;
use Goat\Support\GoatConfig;
use Goat\Support\StubRenderer;

final class RequestGenerator
{
    public function __construct(
        private readonly StubRenderer $stubs,
    ) {}

    /**
     * @return array<int, array{path:string, contents:string}>
     */
    public function generate(GoatTable $table): array
    {
        $model = $table->modelName;
        $namespace = $this->namespace();
        $storePath = $this->path("Store{$model}Request");
        $updatePath = $this->path("Update{$model}Request");
        $storeRules = $this->buildRules($table, false);
        $updateRules = $this->buildRules($table, true);
        $storeContents = $this->stubs->render('request', [
            'namespace' => $namespace,
            'class' => "Store{$model}Request",
            'rules' => $storeRules,
        ]);
        $updateContents = $this->stubs->render('request', [
            'namespace' => $namespace,
            'class' => "Update{$model}Request",
            'rules' => $updateRules,
        ]);
        return [
            ['path' => $storePath, 'contents' => $storeContents],
            ['path' => $updatePath, 'contents' => $updateContents],
        ];
    }

    private function namespace(): string
    {
        return GoatConfig::string('goat.namespaces.request', 'App\\Http\\Requests');
    }

    private function path(string $class): string
    {
        $base = $this->resolvePath('request', 'app/Http/Requests');
        return rtrim($base, '/') . '/' . $class . '.php';
    }

    private function resolvePath(string $key, string $fallback): string
    {
        $configured = GoatConfig::string("goat.paths.{$key}", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'request' && function_exists('app_path')) {
            try { return \app_path('Http/Requests'); } catch (\Throwable) {}
        }
        $base = function_exists('base_path') ? (function() use ($fallback) { try { return \base_path($fallback); } catch (\Throwable) { return getcwd() . '/' . $fallback; } })() : getcwd() . '/' . $fallback;
        return rtrim((string) $base, '/');
    }

    private function buildRules(GoatTable $table, bool $isUpdate): string
    {
        $lines = [];
        foreach ($table->fillableColumns() as $col) {
            $rules = [];
            if ($isUpdate) {
                $rules[] = 'sometimes';
                if ($col->nullable) {
                    $rules[] = 'nullable';
                }
            } else {
                $rules[] = $col->nullable ? 'nullable' : 'required';
            }
            $typeRules = $this->typeRules($col);
            foreach ($typeRules as $tr) {
                $rules[] = $tr;
            }
            if ($col->type === 'string' && $col->length !== null) {
                $rules[] = "max:{$col->length}";
            }
            if ($col->unique) {
                $rules[] = "unique:{$table->name},{$col->name}";
            }
            if ($col->isForeignKey && $col->foreignTable) {
                $foreignColumn = $col->foreignColumn ?? 'id';
                $rules[] = "exists:{$col->foreignTable},{$foreignColumn}";
            }
            if ($col->type === 'enum' && $col->enumValues !== null) {
                $vals = implode(',', $col->enumValues);
                $rules[] = "in:{$vals}";
            }
            $rulesExport = "['" . implode("', '", $rules) . "']";
            $lines[] = "            '{$col->name}' => {$rulesExport},";
        }
        if (empty($lines)) {
            return "            // no rules";
        }
        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    private function typeRules(\Goat\Schema\GoatColumn $col): array
    {
        return match ($col->type) {
            'string', 'char', 'text', 'uuid', 'ulid' => ['string'],
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'unsignedBigInteger', 'foreignId' => ['integer'],
            'decimal', 'float', 'double' => ['numeric'],
            'boolean' => ['boolean'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'json', 'jsonb' => ['array'],
            'enum' => ['string'],
            default => ['string'],
        };
    }
}
