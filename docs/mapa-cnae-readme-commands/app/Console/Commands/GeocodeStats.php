<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeStats extends Command
{
    protected $signature = 'geocode:stats';
    protected $description = 'Estatísticas do cache de geocoding (SQLite)';

    public function handle()
    {
        $conn  = DB::connection('sqlite');

        $total = (int) $conn->table('geocode_cache')->count();
        $ok    = (int) $conn->table('geocode_cache')->whereNotNull('lat')->whereNotNull('lng')->count();
        $nulls = $total - $ok;

        $this->table(['Total','Com coordenadas','Sem coordenadas'], [[ $total, $ok, $nulls ]]);

        $latest = $conn->table('geocode_cache')
            ->select(['input_address','formatted_address','lat','lng','updated_at'])
            ->orderByDesc('id')->limit(10)->get();

        $latestRows = $latest->map(function ($r) {
            return [
                (string) $r->input_address,
                (string) ($r->formatted_address ?? ''),
                $r->lat,
                $r->lng,
                (string) $r->updated_at,
            ];
        })->all();

        $this->info('Últimos 10 registros:');
        $this->table(['input_address','formatted_address','lat','lng','updated_at'], $latestRows);

        return self::SUCCESS;
    }
}
