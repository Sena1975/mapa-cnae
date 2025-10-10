<?php

namespace App\Http\Controllers;

use App\Models\PlaceCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternoController extends Controller
{
    protected function keywordsForCnae(?string $cnae): array
    {
        $map = config('cnae_keywords', []);
        if (!$cnae) return $map['_default'] ?? ['comércio'];
        return $map[$cnae] ?? ($map['_default'] ?? ['comércio']);
    }

    protected function makeQueryHash(array $data): string
    {
        return hash('sha256', json_encode($data));
    }

    protected function ttlHours(): int
    {
        return (int) env('PLACES_CACHE_TTL_HOURS', 168);
    }

    /** Estima um raio em metros a partir da viewport atual */
    protected function estimateRadius(?float $north, ?float $south, ?float $east, ?float $west): int
    {
        if ($north === null || $south === null || $east === null || $west === null) {
            return 15000; // 15 km padrão
        }
        $latMeters = abs($north - $south) * 111_000;
        $lngMeters = abs($east - $west)   * 111_000 * cos(deg2rad(($north + $south) / 2));
        $radius = (int) round(max($latMeters, $lngMeters) / 2);
        $maxEnv = (int) env('PLACES_MAX_RADIUS_M', 15000);
        return max(1000, min($radius, $maxEnv));
    }

    /**
     * GET /api/externos
     * Parâmetros: cidade, cnae, north/south/east/west (opcional), limit
     */
    public function list(Request $request)
    {
        $cnae   = trim((string) $request->query('cnae', ''));
        $cidade = trim((string) $request->query('cidade', '')); // novo
        // compat com param antigo
        $pracaParam = $request->query('praca');
        if (!$cidade && $pracaParam) $cidade = trim((string) $pracaParam);

        $north = $request->query('north'); $south = $request->query('south');
        $east  = $request->query('east');  $west  = $request->query('west');
        $limit = max(20, min((int) $request->query('limit', 200), 500));

        $northF = $north !== null ? (float) $north : null;
        $southF = $south !== null ? (float) $south : null;
        $eastF  = $east  !== null ? (float) $east  : null;
        $westF  = $west  !== null ? (float) $west  : null;

        $keywords = $this->keywordsForCnae($cnae);
        $radius   = $this->estimateRadius($northF, $southF, $eastF, $westF);

        $results = collect();
        foreach ($keywords as $kw) {
            $hash = $this->makeQueryHash([
                'kw' => $kw, 'cidade' => $cidade,
                'north' => $northF, 'south' => $southF, 'east' => $eastF, 'west' => $westF, 'radius' => $radius
            ]);

            // 1) Cache
            $cached = PlaceCache::where('query_hash', $hash)
                ->orderByDesc('id')
                ->get();

            $freshEnough = $cached->isNotEmpty() &&
                now()->subHours($this->ttlHours())->lte($cached->first()->created_at);

            if ($freshEnough) {
                $results = $results->concat($cached);
            } else {
                // 2) Live (Places) se habilitado
                if (filter_var(env('PLACES_LIVE', true), FILTER_VALIDATE_BOOLEAN)) {
                    $fetched = $this->fetchFromPlaces($kw, $cidade, $northF, $southF, $eastF, $westF, $radius, $hash, $cnae);
                    if ($fetched) $results = $results->concat($fetched);
                }
            }

            if ($results->count() >= $limit) break;
        }

        // Resposta padronizada
        $data = $results->take($limit)->map(function ($r) {
            return [
                'nome' => $r->name,
                'lat'  => $r->lat ? (float) $r->lat : null,
                'lng'  => $r->lng ? (float) $r->lng : null,
                'cnae' => $r->cnae,
                'faturamento_medio' => null,
                'data_ult_compra'   => null,
                // campos de endereço não disponíveis via Text Search (mantemos null)
                'endereco' => null, 'numero' => null, 'bairro' => null, 'cidade' => $r->praca, 'uf' => null, 'cep' => null,
                'origem'  => 'externo',
            ];
        });

        return response()->json($data->values());
    }

    /** Chama Google Places Text Search (server-side) e persiste em cache (SQLite) */
    protected function fetchFromPlaces(
        string $keyword,
        string $cidade,
        ?float $north,
        ?float $south,
        ?float $east,
        ?float $west,
        int $radius,
        string $hash,
        ?string $cnae
    ) {
        $apiKey = env('GOOGLE_PLACES_KEY', env('GOOGLE_MAPS_KEY'));
        if (!$apiKey) return collect();

        $query = $keyword . ($cidade ? (' ' . $cidade) : '');

        // Centro aproximado para o radius (se viewport informada)
        $latCenter = null; $lngCenter = null;
        if ($north !== null && $south !== null && $east !== null && $west !== null) {
            $latCenter = ($north + $south) / 2.0;
            $lngCenter = ($east  + $west)  / 2.0;
        }

        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
        $params = [
            'query' => $query,
            'key'   => $apiKey,
            'language' => 'pt-BR',
            'region'   => 'br',
        ];
        if ($latCenter !== null && $lngCenter !== null) {
            $params['location'] = $latCenter . ',' . $lngCenter;
            $params['radius']   = $radius;
        }

        $resp = Http::timeout(10)->get($url, $params);
        if (!$resp->ok()) return collect();

        $json = $resp->json();
        if (!isset($json['results']) || !is_array($json['results'])) return collect();

        $rows = collect($json['results'])->map(function ($p) use ($hash, $keyword, $cidade, $north, $south, $east, $west, $radius, $cnae) {
            $lat = data_get($p, 'geometry.location.lat');
            $lng = data_get($p, 'geometry.location.lng');
            return PlaceCache::create([
                'cnae'      => $cnae,
                'keyword'   => $keyword,
                'query_hash'=> $hash,
                'place_id'  => $p['place_id'] ?? (md5(($p['name'] ?? '') . $lat . $lng)),
                'name'      => $p['name'] ?? null,
                'lat'       => $lat,
                'lng'       => $lng,
                'address'   => $p['formatted_address'] ?? null,
                'types'     => isset($p['types']) ? implode(',', $p['types']) : null,
                // usamos a coluna 'praca' existente para armazenar a cidade (evita migration)
                'praca'     => $cidade ?: null,
                'north'     => $north, 'south' => $south, 'east' => $east, 'west' => $west,
                'radius_m'  => $radius,
                'source'    => 'places',
            ]);
        });

        return $rows;
    }
}
