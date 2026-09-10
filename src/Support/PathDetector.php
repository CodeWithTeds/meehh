<?php

declare(strict_types=1);

namespace Goat\Support;

use Illuminate\Filesystem\Filesystem;

final class PathDetector
{
    public function __construct(
        private readonly Filesystem $files = new Filesystem(),
    ) {}

    /**
     * Discover immediate and nested subfolders (up to $maxDepth) under $basePath.
     *
     * @return array<string, string> map of relative path => absolute path (e.g., 'Admin' => '/var/app/app/Http/Controllers/Admin')
     */
    public function discover(string $basePath, int $maxDepth = 2): array
    {
        if (! is_dir($basePath)) {
            return [];
        }

        $result = [];
        $this->collect($basePath, $basePath, $result, 1, $maxDepth);
        ksort($result);

        return $result;
    }

    /**
     * Convenience: return only folder names (relative keys) sorted.
     *
     * @return string[]
     */
    public function folderNames(string $basePath, int $maxDepth = 2): array
    {
        return array_keys($this->discover($basePath, $maxDepth));
    }

    /**
     * Check if a given subfolder exists under base.
     */
    public function exists(string $basePath, string $subfolder): bool
    {
        $candidate = rtrim($basePath, '/') . '/' . trim($subfolder, '/');
        return is_dir($candidate);
    }

    /**
     * Get relative display path (relative to base_path or cwd).
     */
    public static function relativeDisplay(string $absolute): string
    {
        $display = $absolute;
        if (function_exists('base_path')) {
            try {
                $base = \base_path();
                if ($base !== '' && str_starts_with($display, rtrim($base, '/'))) {
                    $display = ltrim(substr($display, strlen(rtrim($base, '/'))), '/');
                }
            } catch (\Throwable) {}
        } else {
            $cwd = getcwd();
            if ($cwd && str_starts_with($display, rtrim($cwd, '/'))) {
                $display = ltrim(substr($display, strlen(rtrim($cwd, '/'))), '/');
            }
        }

        return $display !== '' ? $display : $absolute;
    }

    /**
     * @param array<string,string> $result
     */
    private function collect(string $base, string $current, array &$result, int $depth, int $maxDepth): void
    {
        if ($depth > $maxDepth) {
            return;
        }

        try {
            $dirs = $this->files->directories($current);
        } catch (\Throwable) {
            return;
        }

        foreach ($dirs as $dir) {
            $basename = basename($dir);
            // Skip hidden and vendor
            if ($basename === '' || str_starts_with($basename, '.')) {
                continue;
            }
            if ($basename === 'vendor' || $basename === 'node_modules') {
                continue;
            }

            $relative = ltrim(substr($dir, strlen(rtrim($base, '/'))), '/');
            if ($relative === '') {
                continue;
            }
            // Normalize to forward slashes
            $relative = str_replace('\\', '/', $relative);
            $result[$relative] = $dir;

            // Recurse deeper
            $this->collect($base, $dir, $result, $depth + 1, $maxDepth);
        }
    }
}
