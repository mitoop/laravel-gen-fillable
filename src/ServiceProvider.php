<?php

namespace Mitoop\LaravelGenFillable;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Mitoop\LaravelGenFillable\Commands\GenerateModelCommand;
use Mitoop\LaravelGenFillable\Commands\GenerateModelFillableCommand;

class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateModelCommand::class,
                GenerateModelFillableCommand::class,
            ]);
        }
    }
}
