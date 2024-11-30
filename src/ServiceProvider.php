<?php

namespace Mitoop\LaravelGenFillable;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Mitoop\LaravelGenFillable\Commands\GenerateFillable;

class ServiceProvider extends LaravelServiceProvider
{
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFillable::class,
            ]);
        }
    }
}
