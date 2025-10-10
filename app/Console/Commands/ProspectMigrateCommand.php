<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProspectMigrateCommand extends Command
{
    protected $signature = 'prospect:migrate {--fresh : Zera e recria as tabelas de prospecção}';
    protected $description = 'Roda as migrations do módulo de prospecção (SQLite).';

    public function handle(): int
    {
        $database = 'sqlite_prospect';
        $path = database_path('migrations_prospect');

        if (! file_exists($path)) {
            $this->error("Pasta de migrations não encontrada: $path");
            return self::FAILURE;
        }

        // garante que o arquivo .sqlite existe
        $dbFile = config('database.connections.sqlite_prospect.database');
        if ($dbFile && ! file_exists($dbFile)) {
            @touch($dbFile);
            $this->info("Criado arquivo SQLite: $dbFile");
        }

        if ($this->option('fresh')) {
            // Opcional: reset total das tabelas do módulo
            Artisan::call('migrate:fresh', [
                '--database' => $database,
                '--path'     => $path,
                '--force'    => true,
            ]);
        } else {
            // Migrate normal (cria a tabela migrations se não existir)
            Artisan::call('migrate', [
                '--database' => $database,
                '--path'     => $path,
                '--force'    => true,
            ]);
        }

        $this->line(Artisan::output());
        $this->info("Migrations executadas na conexão {$database}.");
        return self::SUCCESS;
    }
}
