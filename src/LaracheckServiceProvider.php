<?php

namespace Sophireak\Laracheck;

use Illuminate\Support\ServiceProvider;
use Sophireak\Laracheck\Commands\LaracheckCommand;
use Sophireak\Laracheck\Commands\InstallCommand;
use Sophireak\Laracheck\Commands\BranchCommand;
use Sophireak\Laracheck\Commands\CommitCommand;

class LaracheckServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laracheck.php',
            'laracheck'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaracheckCommand::class,
                InstallCommand::class,
                BranchCommand::class,
                CommitCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/laracheck.php' => config_path('laracheck.php'),
            ], 'laracheck-config');

            $this->publishes([
                __DIR__ . '/../stubs/pre-push' => base_path('.githooks/pre-push'),
            ], 'laracheck-hooks');
        }
    }
}
