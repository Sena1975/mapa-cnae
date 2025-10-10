<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ClientesExport;
use App\Models\GeocodeCache;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $googleKey = config('services.google.maps_key');
        return view('mapa.index', compact('googleKey'));
    }

    protected function resolveFrom(string $table): string
    {
        $schema = env('ORACLE_SCHEMA');
        if ($schema) return $schema . '.' . $table;

        try {
            $row = DB::connection('oracle')->selectOne(
                "SELECT TABLE_OWNER, TABLE_NAME
                   FROM ALL_SYNONYMS
                  WHERE (OWNER = USER OR OWNER = 'PUBLIC')
                    AND SYNONYM_NAME = UPPER(?)",
                [$table]
            );
            if ($row && isset($row->TABLE_OWNER, $row->TABLE_NAME)) {
                return $row->TABLE_OWNER . '.' . $row->TABLE_NAME;
            }
        } catch (\Throwable $e) {}
        return $table;
    }

    /* ================= Helpers ================= */

    protected function ciLike($column, $value): array
    {
        return ["UPPER($column) LIKE UPPER(?)", ["%{$value}%"]];
    }

    protected function normalizeAddress(string $addr): string
    {
        $a = mb_strtolower(trim($addr), 'UTF-8');
        return preg_replace('/\s+/', ' ', $a);
    }

    /** Monta o endereço completo a partir dos campos da view Oracle */
    protected function buildFullAddress($r): ?string
    {
        // Ex.: "Rua X, 123 - Bairro, Cidade - UF, 00000-000, Brasil"
        $addr = '';
        if (!empty($r->endereco)) $addr .= trim($r->endereco);
        if (!empty($r->numero))   $addr .= ', ' . trim($r->numero);
        if (!empty($r->bairro))   $addr .= ' - ' . trim($r->bairro);

        $cidadeUf = trim(implode(' - ', array_filter([$r->cidade ?? null, $r->uf ?? null])));
        if ($cidadeUf) $addr .= ', ' . $cidadeUf;

        if (!empty($r->cep)) $addr .= ', ' . trim($r->cep);
        $addr = trim($addr);

        if (mb_strlen($addr) < 5) return null;
        return $addr . ', Brasil';
    }

    /** Geocodifica com cache (SQLite). Retorna ['lat'=>?, 'lng'=>?] */
/**
 * Geocodifica um endereço com cache em SQLite.
 * Respeita:
 *  - GEOCODE_CACHE_ONLY=true  -> nunca chama a API, usa só o cache
 *  - GEOCODE_CACHE_TTL_HOURS -> 0 = infinito (não revalida)
 */
protected function geocode(string $fullAddress): array
{
    // normaliza e cria a chave do cache
    $addrNorm  = $this->normalizeAddress($fullAddress);
    $hash      = hash('sha256', $addrNorm);
    $ttlHours  = (int) env('GEOCODE_CACHE_TTL_HOURS', 0);
    $cacheOnly = filter_var(env('GEOCODE_CACHE_ONLY', false), FILTER_VALIDATE_BOOLEAN);

    // 1) tenta cache
    $cached = GeocodeCache::where('addr_hash', $hash)->first();
    if ($cached) {
        // válido pelo TTL ou infinito
        if ($ttlHours === 0 || now()->subHours($ttlHours)->lte($cached->updated_at)) {
            return [
                'lat' => $cached->lat !== null ? (float) $cached->lat : null,
                'lng' => $cached->lng !== null ? (float) $cached->lng : null,
            ];
        }
        // cache-only: devolve o que tiver no cache (mesmo expirado) e não chama API
        if ($cacheOnly) {
            return [
                'lat' => $cached->lat !== null ? (float) $cached->lat : null,
                'lng' => $cached->lng !== null ? (float) $cached->lng : null,
            ];
        }
    }

    // 2) se só cache, não chama geocoding externo
    if ($cacheOnly) {
        // opcional: criar entrada nula para evitar reprocesso futuro
        if (!$cached) {
            GeocodeCache::create([
                'addr_hash'         => $hash,
                'input_address'     => $fullAddress,
                'formatted_address' => null,
                'lat'               => null,
                'lng'               => null,
                'provider'          => 'google',
            ]);
        }
        return ['lat' => null, 'lng' => null];
    }

    // 3) chama API (somente se não for cache-only)
    $apiKey = env('GOOGLE_GEOCODE_KEY', env('GOOGLE_MAPS_KEY'));
    if (!$apiKey) {
        return ['lat' => null, 'lng' => null];
    }

    try {
        $resp = \Illuminate\Support\Facades\Http::timeout(8)->get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            [
                'address'  => $fullAddress,
                'key'      => $apiKey,
                'language' => 'pt-BR',
                'region'   => 'br',
            ]
        );

        if ($resp->ok() && isset($resp['results'][0]['geometry']['location'])) {
            $res = $resp['results'][0];
            $lat = (float) data_get($res, 'geometry.location.lat');
            $lng = (float) data_get($res, 'geometry.location.lng');

            GeocodeCache::updateOrCreate(
                ['addr_hash' => $hash],
                [
                    'input_address'     => $fullAddress,
                    'formatted_address' => $res['formatted_address'] ?? null,
                    'lat'               => $lat,
                    'lng'               => $lng,
                    'provider'          => 'google',
                ]
            );

            return ['lat' => $lat, 'lng' => $lng];
        }
    } catch (\Throwable $e) {
        // silencioso (não quebra a resposta)
    }

    // 4) falha/sem resultado: grava nulo (para não insistir) e retorna null
    if (!$cached) {
        GeocodeCache::create([
            'addr_hash'         => $hash,
            'input_address'     => $fullAddress,
            'formatted_address' => null,
            'lat'               => null,
            'lng'               => null,
            'provider'          => 'google',
        ]);
    }

    return ['lat' => null, 'lng' => null];
}
    

    /* ================= Endpoint principal ================= */

    /** GET /api/clientes */
    public function list(Request $request)
    {
        // filtros (agora com cidade)
        $cidade   = $request->query('cidade');
        $cnae     = $request->query('cnae');
        $equipe   = $request->query('equipe');     // SUPERVISOR
        $vendedor = $request->query('vendedor');
        $ramo     = $request->query('ramo');

        $limit = (int) ($request->query('limit', 2000));
        $limit = max(100, min($limit, 5000));
        $debug = (bool) $request->query('debug', false);

        $tableName = env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA');
        $from      = $this->resolveFrom($tableName);

        try {
            $q = DB::connection('oracle')->table(DB::raw($from));

            // LIKE para texto
            if ($cidade)  { [$sql,$bind] = $this->ciLike('CIDADE', $cidade);               $q->whereRaw($sql,$bind); }
            if ($equipe)  { [$sql,$bind] = $this->ciLike('SUPERVISOR', $equipe);           $q->whereRaw($sql,$bind); }
            if ($vendedor){ [$sql,$bind] = $this->ciLike('VENDEDOR', $vendedor);           $q->whereRaw($sql,$bind); }
            if ($ramo)    { [$sql,$bind] = $this->ciLike('RAMO_ATIVIDADE', $ramo);         $q->whereRaw($sql,$bind); }

            // CNAE exato (troque por LIKE se quiser)
            if ($cnae)    { $q->where('CNAE', $cnae); }

            $nomeExpr = DB::raw('COALESCE(NOME_FANTASIA, RAZAO_SOCIAL) as nome_exibicao');

            $rows = $q->select([
                    $nomeExpr,
                    DB::raw('CNAE                  as cnae'),
                    DB::raw('FATURAMENTO_MEDIO     as faturamento_medio'),
                    DB::raw('DATA_ULTIMA_COMPRA     as data_ultima_compra'),
                    DB::raw('ENDERECO              as endereco'),
                    DB::raw('NUMERO                as numero'),
                    DB::raw('BAIRRO                as bairro'),
                    DB::raw('CIDADE                as cidade'),
                    DB::raw('UF                    as uf'),
                    DB::raw('CEP                   as cep'),
                ])
                ->orderBy('DATA_ULTIMA_COMPRA', 'desc')
                ->limit($limit)
                ->get();

            $data = $rows->map(function ($r) use ($debug) {
                $nome = $r->nome_exibicao ?? '—';
                $full = $this->buildFullAddress($r);

                $lat = null; $lng = null; //$ok = false;
                if ($full) {
                    $geo = $this->geocode($full);
                    $lat = $geo['lat']; $lng = $geo['lng'];
                    $ok  = $lat !== null && $lng !== null;
                }

                $payload = [
                    'nome' => $nome,
                    'cnae' => $r->cnae,
                    'faturamento_medio' => $r->faturamento_medio !== null ? (float) $r->faturamento_medio : null,
                    'data_ult_compra'   => $r->data_ultima_compra ? date('Y-m-d', strtotime($r->data_ultima_compra)) : null,

                    'endereco' => $r->endereco,
                    'numero'   => $r->numero,
                    'bairro'   => $r->bairro,
                    'cidade'   => $r->cidade,
                    'uf'       => $r->uf,
                    'cep'      => $r->cep,

                    'lat'      => $lat,
                    'lng'      => $lng,
                    'origem'   => 'interno',
                ];

                if ($debug) {
                    $payload['full_address'] = $full;
                    $payload['geocode_ok']   = $ok;
                }

                return $payload;
            });

            return response()->json($data);

        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-00942') || str_contains($e->getMessage(), 'ORA-01775')) {
                return response()->json([
                    '_warning' => "Falha ao acessar {$from}: {$e->getMessage()}",
                ], 500);
            }
            throw $e;
        }
    }

    public function exportExcel(Request $request)
    {
        $includeExternals = filter_var($request->query('include_externals', 'false'), FILTER_VALIDATE_BOOLEAN);
        $filters = $request->only(['cidade','cnae','equipe','vendedor','ramo']);

        return Excel::download(new ClientesExport($filters, $includeExternals), 'clientes_mapa.xlsx');
    }
}
