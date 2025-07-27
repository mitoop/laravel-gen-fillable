<?php

namespace Mitoop\LaravelGenFillable\Commands;

use Illuminate\Console\Command;

class GenerateModelCommand extends Command
{
    protected $signature = 'gen:model {name : Model class name}';

    protected $description = 'Generate a model and add fillable properties';

    public function handle(): void
    {
        $model = $this->argument('name');

        $this->call('make:model', ['name' => $model]);

        $this->call('gen:fillable', ['model' => $model]);
    }
}
