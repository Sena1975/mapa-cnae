<?php

namespace App\Http\Controllers\Prospect;

use App\Http\Controllers\Controller;
use App\Services\Prospect\FreeEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClienteExternoController extends Controller
{
    /**
     * Popula/atualiza base de externos via Google Places, filtrando por CNAE e região.
     * Espera: lat, lng, cnae, (radius opcional)
     */
    public function buscar(Request $req, FreeEnrichmentService $svc)
    {
        $lat    = (float) $req->get('lat');
        $lng    = (float) $req->get('lng');
        $cnae   = (string) $req->get('cnae');
        $radius = $req->has('radius') ? (int) $req->get('radius') : null;

        if (!$lat || !$lng || !$cnae) {
            return response()->json(['error' => 'Parâmetros obrigatórios: lat, lng, cnae'], 422);
        }

        // Internos para excluir por "fuzzy" (opcional)
        $internos = [];
        try {
            $kmBox = 25;
            $internos = DB::connection('oracle')
                ->table(env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA'))
                ->selectRaw('NOME as nome, CNPJ as cnpj, LATITUDE as lat, LONGITUDE as lng, CNAE as cnae')
                ->where('CNAE', $cnae)
                ->whereNotNull('LATITUDE')
                ->whereNotNull('LONGITUDE')
                ->whereBetween('LATITUDE', [$lat - $kmBox * 0.009, $lat + $kmBox * 0.009])
                ->whereBetween('LONGITUDE', [$lng - $kmBox * 0.009, $lng + $kmBox * 0.009])
                ->limit(2000)
                ->get()
                ->map(fn($r) => ['nome'=>$r->nome,'cnpj'=>$r->cnpj,'lat'=>(float)$r->lat,'lng'=>(float)$r->lng])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('ClienteExternoController: falha ao ler internos', ['err'=>$e->getMessage()]);
        }

        $externos = $svc->buscarPorCnae($lat, $lng, $cnae, $radius, $internos);

        $salvos = 0;
        foreach ($externos as $p) {
            try {
                // Mapeia campos para o seu schema
                $nome   = $p['nome'] ?? null;
                $addr   = $p['endereco'] ?? null;
                $latE   = $p['lat'] ?? null;
                $lngE   = $p['lng'] ?? null;
                $place  = $p['place_id'] ?? null;

                // NÃO temos CNPJ do Places — deixa null
                $cnpj = null;

                DB::connection('sqlite_prospect')
                    ->table('cliente_externo')
                    ->updateOrInsert(
                        // dedupe por place_id se existir; senão, pela combinação nome+lat+lng
                        $place ? ['place_id' => $place] : [
                            'nome_fantasia' => $nome,
                            'latitude'      => $latE,
                            'longitude'     => $lngE,
                        ],
                        [
                            'cnpj'            => $cnpj, // null
                            'razao_social'    => $nome,
                            'nome_fantasia'   => $nome,
                            'codigo_cnae'     => preg_replace('/\D/','', $cnae),
                            'descricao_cnae'  => null, // pode preencher via mapearCnaeParaPalavraChave se desejar
                            'endereco'        => $addr,
                            'numero'          => null,
                            'bairro'          => null,
                            'cidade'          => null,
                            'uf'              => null,
                            'cep'             => null,
                            'ibge'            => null,
                            'latitude'        => $latE,
                            'longitude'       => $lngE,
                            'inscricao_estadual'   => null,
                            'media_compra_mensal'  => null,
                            'source_cnpj'     => null,
                            'source_endereco' => 'google_places',
                            'source_geocode'  => 'google_places',
                            'enriched_at'     => now(),
                            'updated_at'      => now(),
                            'created_at'      => now(),
                            'place_id'        => $place,
                        ]
                    );
                $salvos++;
            } catch (\Throwable $e) {
                Log::warning('Falha ao salvar cliente_externo', [
                    'place_id' => $p['place_id'] ?? null,
                    'err'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['salvos'=>$salvos, 'coletados'=>count($externos)]);
    }

    /**
     * Lista registros já persistidos dentro do bounding box
     * Parâmetros: south, west, north, east, (cnae opcional)
     */
    public function listInBounds(Request $req)
    {
        $south = (float) $req->query('south');
        $west  = (float) $req->query('west');
        $north = (float) $req->query('north');
        $east  = (float) $req->query('east');

        if (!($south || $south===0) || !($west || $west===0) || !($north || $north===0) || !($east || $east===0)) {
            return response()->json(['error' => 'Parâmetros obrigatórios: south, west, north, east'], 422);
        }

        $cnae   = $req->query('cnae');
        $limit  = min((int) $req->query('limit', 500), 1000);
        $offset = max((int) $req->query('offset', 0), 0);

        $q = DB::connection('sqlite_prospect')->table('cliente_externo')
            ->whereBetween('latitude',  [$south, $north])
            ->whereBetween('longitude', [$west,  $east]);

        if ($cnae) {
            $q->where('codigo_cnae', preg_replace('/\D/','', $cnae));
        }

        $rows = $q->orderByDesc('updated_at')->offset($offset)->limit($limit)->get();

        return response()->json([
            'count' => $rows->count(),
            'data'  => $rows,
        ]);
    }

    /**
     * Contagem dentro do bounding box
     */
    public function countInBounds(Request $req)
    {
        $south = (float) $req->query('south');
        $west  = (float) $req->query('west');
        $north = (float) $req->query('north');
        $east  = (float) $req->query('east');

        if (!($south || $south===0) || !($west || $west===0) || !($north || $north===0) || !($east || $east===0)) {
            return response()->json(['error' => 'Parâmetros obrigatórios: south, west, north, east'], 422);
        }

        $cnae = $req->query('cnae');

        $q = DB::connection('sqlite_prospect')->table('cliente_externo')
            ->whereBetween('latitude',  [$south, $north])
            ->whereBetween('longitude', [$west,  $east]);

        if ($cnae) {
            $q->where('codigo_cnae', preg_replace('/\D/','', $cnae));
        }

        $total   = (clone $q)->count();
        $porCnae = (clone $q)
            ->selectRaw('codigo_cnae as cnae, COUNT(*) as total')
            ->groupBy('codigo_cnae')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json([
            'total'    => $total,
            'por_cnae' => $porCnae,
        ]);
    }
}
