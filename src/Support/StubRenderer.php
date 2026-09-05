<?php

declare(strict_types=1);

namespace Goat\Support;

use Illuminate\Filesystem\Filesystem;

final class StubRenderer
{
    public function __construct(
        private readonly Filesystem $files = new Filesystem(),
    ) {}

    /**
     * Render a stub with {{ placeholders }}.
     *
     * Lookup order:
     *  1) resources/stubs/vendor/goat/<stub>
     *  2) package stubs/<stub>
     */
    public function render(string $stubName, array $replacements = []): string
    {
        $path = $this->resolvePath($stubName);

        if (! $this->files->exists($path)) {
            throw new \RuntimeException("Stub [{$stubName}] not found at [{$path}].");
        }

        $contents = $this->files->get($path);

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ ' . $key . ' }}', (string) $value, $contents);
            $contents = str_replace('{{' . $key . '}}', (string) $value, $contents);
            // Also support raw without spaces already done
        }

        return $contents;
    }

    public function resolvePath(string $stubName): string
    {
        // Normalize: ensure .stub suffix
        if (! str_ends_with($stubName, '.stub')) {
            $stubName .= '.stub';
        }

        // 1) Published customized stub
        $customBase = $this->customStubsPath();
        $customPath = rtrim($customBase, '/') . '/' . $stubName;

        if ($this->files->exists($customPath)) {
            return $customPath;
        }

        // 2) Package default
        $packagePath = dirname(__DIR__, 2) . '/stubs/' . $stubName;

        return $packagePath;
    }

    private function customStubsPath(): string
    {
        $configured = GoatConfig::string('goat.stubs_path', '');
        if ($configured !== '') {
            return $configured;
        }

        if (function_exists('resource_path')) {
            try { return \resource_path('stubs/vendor/goat'); } catch (\Throwable) {}
        }

        return getcwd() . '/resources/stubs/vendor/goat';
    }

    public function exists(string $stubName): bool
    {
        return $this->files->exists($this->resolvePath($stubName));
    }
}
