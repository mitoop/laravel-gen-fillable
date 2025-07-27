<?php

namespace Mitoop\LaravelGenFillable;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Mitoop\LaravelGenFillable\Commands\GenerateModelFillable;
use Mitoop\LaravelGenFillable\Commands\GenerateModelFillableFromTable;

class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateModelFillable::class,
                GenerateModelFillableFromTable::class,
            ]);
        }
    }
}
