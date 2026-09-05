<?php

declare(strict_types=1);

namespace Goat\Commands;

use Goat\Support\GoatBanner;
use Illuminate\Console\Command;

/**
 * Standalone banner: `php artisan goat` and `php artisan goat:banner`
 *
 * Shows the modern side-by-side UI (meeeh.png on the left, intro on the right)
 * without requiring a model name. Useful for `goat:make` preview and for
 * shell startup (add `php artisan goat --compact` to ~/.zshrc).
 */
final class BannerCommand extends Command
{
    protected $signature = 'goat:banner
                            {name? : Optional model name to preview in the banner}
                            {--compact : Compact card (fewer lines)}
                            {--no-image : Hide the goat image}';

    protected $description = 'Show GOAT banner (meeeh.png + intro card)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $compact = (bool) $this->option('compact');
        $withImage = ! (bool) $this->option('no-image');

        // Delegate to GoatBanner - it handles image vs stacked fallback
        try {
            GoatBanner::render($this->output, is_string($name) ? $name : null, $compact, $withImage);
        } catch (\Throwable $e) {
            $this->line('<fg=yellow>🐐 GOAT</> <fg=gray>— https://github.com/CodeWithTeds/meehh</>');
            if ($this->output->isVerbose()) {
                $this->line('<fg=red>'.$e->getMessage().'</>');
            }
        }

        // Helpful next steps when not compact
        if (! $compact) {
            $this->line('  <fg=gray>Try:</> <fg=cyan>php artisan goat:make Product</>  <fg=gray>or</>  <fg=cyan>php artisan goat --help</>');
            $this->line('');
            $this->line('  <fg=gray>Shell startup:</> add <fg=white>php artisan goat --compact</> to <fg=white>~/.zshrc</> or <fg=white>~/.bashrc</> to see this on terminal open.');
        }

        return self::SUCCESS;
    }
}
