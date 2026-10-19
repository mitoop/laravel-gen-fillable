<?php

namespace Mitoop\LaravelGenFillable\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Mitoop\LaravelGenFillable\FillableWriter;
use ReflectionClass;

class GenerateModelFillableCommand extends Command
{
    protected $signature = 'gen:fillable {name : Model class name (e.g. User) or table name (e.g. users)}';

    protected $description = 'Generate fillable for a model or table';

    public function handle(): void
    {
        $name = $this->argument('name');

        if ($this->isModel($name)) {
            $this->generateForModel($name);

            return;
        }

        $this->generateForTable($name);
    }

    protected function isModel(string $name): bool
    {
        return preg_match('/^[A-Z]/', $name) === 1;
    }

    protected function generateForModel(string $model): void
    {
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

        $file = with(new ReflectionClass($fullModel))->getFileName();
        $fileContent = file_get_contents($file);

        if ($message = FillableWriter::alreadyDefined($fileContent)) {
            $this->warn($message);

            return;
        }

        file_put_contents($file, FillableWriter::insert($fileContent, $columns));

        Process::run("./vendor/bin/pint {$file}");

        $this->info(FillableWriter::usesAttribute()
            ? "Inserted #[Fillable] into: {$file}"
            : "Inserted \$fillable into: {$file}");
    }

    protected function generateForTable(string $table): void
    {
        $db = DB::connection();

        if ($db->getDriverName() === 'mysql'
            &&
            version_compare($this->getLaravel()->version(), '10.30.0', '<')) {
            $grammar = new class extends MySqlGrammar
            {
                public function compileColumnListing()
                {
                    return parent::compileColumnListing().' ORDER BY ordinal_position';
                }
            };

            $db->setSchemaGrammar($grammar);

            $db->withTablePrefix($grammar);
        }

        $columns = $db->getSchemaBuilder()->getColumnListing($table);
        $columns = array_diff($columns, ['id', 'created_at', 'updated_at', 'deleted_at']);

        $fillable = FillableWriter::clipboard($columns);

        $this->info($fillable);

        if (PHP_OS === 'Darwin') {
            exec('echo '.escapeshellarg($fillable).' | pbcopy');
            $this->info('The fillable array has been copied to your clipboard.');
        }
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
