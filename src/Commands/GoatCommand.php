<?php

declare(strict_types=1);

namespace Goat\Commands;

use Goat\Support\GoatBanner;
use Illuminate\Console\Command;

/**
 * Entry point `php artisan goat`
 *
 * Mirrors `goat:banner` but uses the bare `goat` signature so users can simply
 * run `php artisan goat` (or wire it to shell startup). Shows the same modern
 * meeeh.png left + intro right card.
 */
final class GoatCommand extends Command
{
    protected $signature = 'goat
                            {name? : Optional model name to preview}
                            {--api : API preset — suggests goat:make --api (JSON Resource controller)}
                            {--web : Web preset — suggests goat:make --web}
                            {--compact : Compact card}
                            {--help-commands : Show available GOAT commands}';

    protected $description = 'GOAT — show banner & help (image left, intro right)';

    public function handle(): int
    {
        $name = $this->argument('name');
        // Normalize if user passes a path like tests/Feature/InventoryTest.php (fixes "goat tests/Feature/InventoryTest.php")
        if (is_string($name) && (str_contains($name, '/') || str_contains($name, '\\') || str_ends_with($name, '.php'))) {
            $base = basename(str_replace('\\', '/', $name));
            $base = preg_replace('/\.php$/i', '', $base) ?? $base;
            $base = preg_replace('/Test$/i', '', $base) ?? $base;
            $this->line("  <fg=yellow>ℹ</> Interpreting <fg=white>{$name}</> as model <fg=cyan>{$base}</> — did you mean <fg=white>php artisan goat:make {$base} --api</>?");
            $name = $base;
        }
        $compact = (bool) $this->option('compact');
        $isApi = (bool) $this->option('api');
        $isWeb = (bool) $this->option('web');

        try {
            GoatBanner::render($this->output, is_string($name) ? $name : null, $compact, true);
        } catch (\Throwable $e) {
            $this->line('<fg=yellow>🐐 GOAT</> <fg=gray>— https://github.com/CodeWithTeds/meehh</>');
            if ($this->output->isVerbose()) {
                $this->line('<fg=red>'.$e->getMessage().'</>');
            }
        }

        if ($isApi && $isWeb) {
            $this->components->error('Cannot use --api and --web together.');
            return self::FAILURE;
        }
        if ($isApi || $isWeb) {
            $preset = $isApi ? 'API' : 'Web';
            $this->line('');
            $this->line("  <fg=green>✓</> <fg=white>{$preset} preset</> — controller is JSON Resource + Service/Repository (thin controller).");
            $this->line("  <fg=gray>Use:</> <fg=cyan>php artisan goat:make " . ($name ? $name : 'Inventory') . ($isApi ? ' --api' : ' --web') . "</> to generate.");
            if ($isApi) {
                $this->line("  <fg=gray>API generates:</> Model, Migration, Requests, Resource, Controller (api), Service, Repository, Policy, Tests");
            }
        }
        if ((bool) $this->option('help-commands') || $name === null) {
            $this->line('  <fg=white;options=bold>Available commands</>');
            $this->line('    <fg=cyan>goat</> / <fg=cyan>goat:banner</>          Show this banner');
            $this->line('    <fg=cyan>goat:make {Product}</>        Generate feature slice (Model+Requests+Resource+Service...)');
            $this->line('    <fg=cyan>goat:make --help</>           Options: --from=migration|erd|path --only --except --force --api --web');
            $this->line('');
            $this->line('  <fg=gray>Examples:</>');
            $this->line('    <fg=white>php artisan goat:make Product</>');
            $this->line('    <fg=white>php artisan goat:make Inventory --api --from=migration --force</>  <fg=gray># full slice at tests/Feature/InventoryTest.php</>');
            $this->line('    <fg=white>php artisan goat Inventory --api</>  <fg=gray># shortcut → suggests goat:make</>');
            $this->line('    <fg=white>cat migration.php | php artisan goat:make Product --from=migration --force</>');
            $this->line('    <fg=white>cat erd.txt | php artisan goat:make Order --from=erd</>');
            $this->line('');
            $this->line('  <fg=white;options=bold>For this project (Inventory):</>');
            $this->line('    <fg=cyan>php artisan goat:make Inventory --api --from=migration --force</>');
            $this->line('    <fg=gray>→ tests/Feature/InventoryTest.php + app/Models/Inventory.php + ...</>');
        }

        if (! $compact && $name === null) {
            $this->line('');
            $this->line('  <fg=gray>Tip:</> add <fg=white>php artisan goat --compact</> to <fg=white>~/.zshrc</> to show on terminal open.');
        }

        return self::SUCCESS;
    }
}
