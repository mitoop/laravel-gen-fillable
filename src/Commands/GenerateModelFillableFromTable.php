<?php

namespace Mitoop\LaravelGenFillable\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class GenerateModelFillableFromTable extends Command
{
    protected $signature = 'gen:fillable-table
                            {table : The table name} 
                            {connection? : The database connection name}';

    protected $description = 'Generate fillable array for a table and copy to clipboard if on macOS';

    public function handle(): void
    {
        $table = $this->argument('table');
        $connection = $this->argument('connection') ?? Config::get('database.default');

        $db = DB::connection($connection);
        if ($db->getDriverName() === 'mysql'
            &&
            version_compare($this->getLaravel()->version(), '10.30.0', '<')) {
            $grammar = new class extends MysqlGrammar
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

        $fillable = "protected \$fillable = [\n    '".implode("',\n    '", $columns)."'\n];";

        $this->info($fillable);

        if (PHP_OS === 'Darwin') {
            exec('echo '.escapeshellarg($fillable).' | pbcopy');
            $this->info('The fillable array has been copied to your clipboard.');
        }
    }
}
