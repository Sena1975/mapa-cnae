<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PlacesStats extends Command
{
    protected $signature = 'places:stats {--top=10}';
    protected $description = 'Estatísticas do cache de externos (places_cache - SQLite)';

    public function handle()
    {
        $top = (int)$this->option('top');
        $conn = DB::connection('sqlite');

        $total = (int) $conn->table('places_cache')->count();

        $byCity = $conn->table('places_cache')
            ->selectRaw('COALESCE(praca,"(sem cidade)") as praca, COUNT(*) as qtd')
            ->groupBy('praca')
            ->orderByDesc('qtd')
            ->limit($top)
            ->get();

        $latest = $conn->table('places_cache')
            ->select(['name','address','lat','lng','praca','updated_at'])
            ->orderByDesc('id')->limit($top)->get();

        $byCityRows = collect($byCity)->map(function ($r) {
            return [ (string) ($r->praca ?? '(sem cidade)'), (int) $r->qtd ];
        })->all();

        $latestRows = collect($latest)->map(function ($r) {
            return [
                (string) ($r->name ?? ''),
                (string) ($r->address ?? ''),
                $r->lat,
                $r->lng,
                (string) ($r->praca ?? ''),
                (string) $r->updated_at,
            ];
        })->all();

        $this->table(['Total externos em cache'], [[ $total ]]);
        $this->info("Top {$top} cidades (places_cache):");
        $this->table(['Cidade','Qtde'], $byCityRows);
        $this->info("Últimos {$top}:"); 
        $this->table(['name','address','lat','lng','praca','updated_at'], $latestRows);

        return self::SUCCESS;
    }
}
