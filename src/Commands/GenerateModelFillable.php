<?php

namespace Mitoop\LaravelGenFillable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

class GenerateModelFillable extends Command
{
    protected $signature = 'gen:fillable {model : The model class name (e.g. User)}';

    protected $description = 'Generate fillable array and insert into model file';

    public function handle(): void
    {
        $model = $this->argument('model');

        $fullModel = $this->resolveFullModelName($model);

        if (! class_exists($fullModel)) {
            $this->error("Model {$fullModel} does not exist.");

            return;
        }

        $model = new $fullModel;
        $table = $model->getTable();
        $connection = $model->getConnectionName() ?: config('database.default');

        $columns = Schema::connection($connection)->getColumnListing($table);
        $columns = array_diff($columns, ['id', 'created_at', 'updated_at', 'deleted_at']);

        $fillableArray = "protected \$fillable = [\n    '".implode("',\n    '", $columns)."'\n];";

        $file = with(new ReflectionClass($fullModel))->getFileName();
        $fileContent = file_get_contents($file);

        if (str_contains($fileContent, 'protected $fillable')) {
            $this->warn('The model already has a $fillable property.');

            return;
        }

        file_put_contents($file, $this->injectFillable($fileContent, $fillableArray));

        Process::run("./vendor/bin/pint {$file}");

        $this->info("Inserted \$fillable into: {$file}");
    }

    protected function injectFillable(string $content, string $fillable): string
    {
        return preg_replace_callback(
            '/(class\s+\w+\s+[^{]*\{)(\s*(?:use\s+[^;]+;\s*)*)/m',
            function ($matches) use ($fillable) {
                $classStart = $matches[1];
                $useBlock = $matches[2];

                return $classStart.$useBlock."\n\n    ".$fillable."\n";
            },
            $content,
            1
        );
    }

    protected function resolveFullModelName(string $class): string
    {
        $fallbacks = [
            'App\\Models\\'.$class,
            'App\\'.$class,
        ];

        foreach ($fallbacks as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return $class;
    }
}
