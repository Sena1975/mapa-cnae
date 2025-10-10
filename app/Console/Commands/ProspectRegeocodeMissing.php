<?php
namespace App\Console\Commands;

use App\Models\Prospect\ClienteExterno;
use App\Services\Prospect\FreeEnrichmentService;
use Illuminate\Console\Command;

class ProspectRegeocodeMissing extends Command
{
    protected $signature = 'prospect:regeocode-missing {--limit=200}';
    protected $description = 'Geocodifica clientes_externos com latitude/longitude nulos.';

    public function handle(FreeEnrichmentService $svc): int
    {
        $limit = (int)$this->option('limit');
        $rows = ClienteExterno::on('sqlite_prospect')
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->limit($limit)
            ->get();

        $ok=0; $skip=0;
        foreach ($rows as $r) {
            $parts = array_filter([$r->endereco, $r->numero, $r->bairro, $r->cidade, $r->uf, $r->cep, 'Brasil']);
            if (!$parts) { $skip++; continue; }
            $q = implode(', ', $parts);
            $geo = $svc->geocodeNominatim($q);
            if (!$geo) { $skip++; continue; }

            $r->latitude  = $geo['lat'];
            $r->longitude = $geo['lon'];
            $r->save();
            $ok++;
        }
        $this->info("Atualizados: $ok | Sem coordenadas: $skip");
        return self::SUCCESS;
    }
}
