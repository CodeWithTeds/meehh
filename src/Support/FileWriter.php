<?php

declare(strict_types=1);

namespace Goat\Support;

use Illuminate\Filesystem\Filesystem;

final class FileWriter
{
    public function __construct(
        private readonly Filesystem $files = new Filesystem(),
    ) {}

    public function ensureDirectory(string $path): void
    {
        $dir = is_dir($path) ? $path : dirname($path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
    }

    /**
     * Write file with optional overwrite protection.
     *
     * @return array{path:string, written:bool, skipped:bool, error:?string}
     */
    public function write(string $path, string $contents, bool $force = false): array
    {
        $dir = dirname($path);

        if (! $this->files->isDirectory($dir)) {
            try {
                $this->files->makeDirectory($dir, 0755, true);
            } catch (\Throwable $e) {
                return [
                    'path' => $path,
                    'written' => false,
                    'skipped' => false,
                    'error' => "Unable to create directory [{$dir}]: {$e->getMessage()}",
                ];
            }
        }

        if ($this->files->exists($path) && ! $force) {
            return [
                'path' => $path,
                'written' => false,
                'skipped' => true,
                'error' => null,
            ];
        }

        try {
            $this->files->put($path, $contents);
        } catch (\Throwable $e) {
            return [
                'path' => $path,
                'written' => false,
                'skipped' => false,
                'error' => "Failed to write file [{$path}]: {$e->getMessage()}",
            ];
        }

        return [
            'path' => $path,
            'written' => true,
            'skipped' => false,
            'error' => null,
        ];
    }

    public function exists(string $path): bool
    {
        return $this->files->exists($path);
    }

    public function relativePath(string $absolutePath): string
    {
        $base = '';
        if (function_exists('base_path')) {
            try { $base = \base_path(); } catch (\Throwable) { $base = getcwd(); }
        } else {
            $base = getcwd();
        }
        $base = rtrim((string) $base, '/');

        if (str_starts_with($absolutePath, $base)) {
            return ltrim(substr($absolutePath, strlen($base)), '/');
        }

        return $absolutePath;
    }
}
