<?php

namespace App\Http\Controllers\Prospect;

use App\Http\Controllers\Controller;
use App\Services\FreeEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClienteExternoController extends Controller
{
    public function buscar(Request $req, FreeEnrichmentService $svc)
    {
        $lat    = (float) $req->get('lat');
        $lng    = (float) $req->get('lng');
        $cnae   = (string) $req->get('cnae');
        $radius = $req->has('radius') ? (int) $req->get('radius') : null;

        if (!$lat || !$lng || !$cnae) {
            return response()->json(['error' => 'Parâmetros obrigatórios: lat, lng, cnae'], 422);
        }

        // 1) Coleta clientes internos perto (para excluir)
        $internos = [];
        try {
            $kmBox = 25; // caixa grossa antes do filtro real (suficiente p/ exclusão)
            $internos = DB::connection('oracle')
                ->table(env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA'))
                ->selectRaw('NOME as nome, CNPJ as cnpj, LATITUDE as lat, LONGITUDE as lng, CNAE as cnae')
                ->where('CNAE', $cnae)
                ->whereNotNull('LATITUDE')
                ->whereNotNull('LONGITUDE')
                // bounding box simples (aproximação)
                ->whereBetween('LATITUDE', [$lat - $kmBox * 0.009, $lat + $kmBox * 0.009])
                ->whereBetween('LONGITUDE', [$lng - $kmBox * 0.009, $lng + $kmBox * 0.009])
                ->limit(2000)
                ->get()
                ->map(fn($r) => [
                    'nome' => $r->nome,
                    'cnpj' => $r->cnpj,
                    'lat'  => (float) $r->lat,
                    'lng'  => (float) $r->lng,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('ClienteExternoController: falha ao ler internos', ['err' => $e->getMessage()]);
        }

        Log::info('ClienteExternoController: internos base p/ exclusão', ['qtd' => count($internos)]);

        // 2) Busca externos (com fallback de raio dentro do serviço)
        $externos = $svc->buscarPorCnae($lat, $lng, $cnae, $radius, $internos);

        Log::info('ClienteExternoController: externos obtidos do Places', ['qtd' => count($externos)]);

        // 3) Persiste/atualiza no SQLite de prospecção
        $salvos = 0;
        foreach ($externos as $p) {
            try {
                DB::connection('prospect') // alias no config/database.php apontando p/ DB_PROSPECT_SQLITE
                    ->table('cliente_externo')
                    ->updateOrInsert(
                        ['place_id' => $p['place_id']],
                        [
                            'nome'       => $p['nome'],
                            'endereco'   => $p['endereco'],
                            'lat'        => $p['lat'],
                            'lng'        => $p['lng'],
                            'cnae'       => $cnae,
                            'fonte'      => 'google_places',
                            'rating'     => $p['rating'] ?? null,
                            'tipos'      => !empty($p['types']) ? json_encode($p['types']) : null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                $salvos++;
            } catch (\Throwable $e) {
                Log::warning('ClienteExternoController: falha ao salvar cliente_externo', [
                    'place_id' => $p['place_id'] ?? null,
                    'err'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('ClienteExternoController: externos persistidos', ['qtd' => $salvos]);

        // 4) Retorna lista pronta para o mapa
        $ret = [];
        try {
            $ret = DB::connection('prospect')
                ->table('cliente_externo')
                ->where('cnae', $cnae)
                ->orderByDesc('updated_at')
                ->limit(500)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('ClienteExternoController: erro ao ler cliente_externo', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'cnae'       => $cnae,
            'lat'        => $lat,
            'lng'        => $lng,
            'radius'     => $radius,
            'salvos'     => $salvos,
            'quantidade' => count($ret),
            'data'       => $ret,
        ]);
    }
}
