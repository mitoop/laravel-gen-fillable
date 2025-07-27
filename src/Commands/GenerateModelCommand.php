<?php

namespace Mitoop\LaravelGenFillable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateModelCommand extends Command
{
    protected $signature = 'gen:model {name : Model class name or table name}';

    protected $description = 'Generate a model and add fillable properties';

    public function handle(): void
    {
        $model = $this->resolveModelName($this->argument('name'));

        $this->call('make:model', ['name' => $model]);

        $this->call('gen:fillable', ['model' => $model]);
    }

    protected function resolveModelName(string $name): string
    {
        if (Str::studly($name) === $name) {
            return $name;
        }

        return Str::singular(Str::studly($name));
    }
}
