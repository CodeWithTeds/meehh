<?php

declare(strict_types=1);

namespace Goat;

use Goat\Commands\BannerCommand;
use Goat\Commands\GoatCommand;
use Goat\Commands\MakeCommand;
use Illuminate\Support\ServiceProvider;

final class GoatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/goat.php', 'goat');

        $this->app->singleton(MakeCommand::class);
        $this->app->singleton(BannerCommand::class);
        $this->app->singleton(GoatCommand::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeCommand::class,
                BannerCommand::class,
                GoatCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/goat.php' => config_path('goat.php'),
            ], 'goat-config');

            $this->publishes([
                __DIR__ . '/../config/goat.php' => config_path('goat.php'),
            ], 'goat');

            $this->publishes([
                __DIR__ . '/../stubs' => resource_path('stubs/vendor/goat'),
            ], 'goat-stubs');

            $this->publishes([
                __DIR__ . '/../stubs' => resource_path('stubs/vendor/goat'),
            ], 'goat');

            // Shell integration for `terminal open` banner
            $this->publishes([
                __DIR__ . '/../shell/goat.sh' => base_path('shell/goat.sh'),
            ], 'goat-shell');
        }
    }
}
