<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeCount extends Command
{
    protected $signature = 'geocode:count';
    protected $description = 'Mostra contagem do cache de geocoding (SQLite)';

    public function handle()
    {
        try {
            $total = (int) DB::connection('sqlite')->table('geocode_cache')->count();
            $ok    = (int) DB::connection('sqlite')->table('geocode_cache')->whereNotNull('lat')->whereNotNull('lng')->count();
            $nulls = $total - $ok;

            $this->table(['Total', 'Com coordenadas', 'Sem coordenadas'], [[ $total, $ok, $nulls ]]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro ao acessar geocode_cache no SQLite: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
