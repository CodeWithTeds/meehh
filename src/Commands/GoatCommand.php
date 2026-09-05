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
                            {--compact : Compact card}
                            {--help-commands : Show available GOAT commands}';

    protected $description = 'GOAT — show banner & help (image left, intro right)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $compact = (bool) $this->option('compact');

        try {
            GoatBanner::render($this->output, is_string($name) ? $name : null, $compact, true);
        } catch (\Throwable $e) {
            $this->line('<fg=yellow>🐐 GOAT</> <fg=gray>— https://github.com/CodeWithTeds/meehh</>');
            if ($this->output->isVerbose()) {
                $this->line('<fg=red>'.$e->getMessage().'</>');
            }
        }

        if ((bool) $this->option('help-commands') || $name === null) {
            $this->line('  <fg=white;options=bold>Available commands</>');
            $this->line('    <fg=cyan>goat</> / <fg=cyan>goat:banner</>          Show this banner');
            $this->line('    <fg=cyan>goat:make {Product}</>        Generate feature slice (Model+Requests+Resource+Service...)');
            $this->line('    <fg=cyan>goat:make --help</>           Options: --from=migration|erd|path --only --except --force');
            $this->line('');
            $this->line('  <fg=gray>Examples:</>');
            $this->line('    <fg=white>php artisan goat:make Product</>');
            $this->line('    <fg=white>cat migration.php | php artisan goat:make Product --from=migration --force</>');
            $this->line('    <fg=white>cat erd.txt | php artisan goat:make Order --from=erd</>');
        }

        if (! $compact && $name === null) {
            $this->line('');
            $this->line('  <fg=gray>Tip:</> add <fg=white>php artisan goat --compact</> to <fg=white>~/.zshrc</> to show on terminal open.');
        }

        return self::SUCCESS;
    }
}
