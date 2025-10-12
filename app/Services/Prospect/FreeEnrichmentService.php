<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FreeEnrichmentService
{
    protected string $apiKey;
    protected bool $live;
    protected int $ttlHours;
    protected int $maxRadius;
    protected int $minRadius;
    protected int $targetMinResults;

    public function __construct()
    {
        $this->apiKey           = env('GOOGLE_PLACES_KEY');
        $this->live             = filter_var(env('PLACES_LIVE', true), FILTER_VALIDATE_BOOLEAN);
        $this->ttlHours         = (int) env('PLACES_CACHE_TTL_HOURS', 168);
        $this->maxRadius        = (int) env('PLACES_MAX_RADIUS_M', 8000);
        $this->minRadius        = max(1000, (int) env('PLACES_MIN_RADIUS_M', 1500));
        $this->targetMinResults = (int) env('PLACES_TARGET_MIN_RESULTS', 8);

        if (empty($this->apiKey)) {
            Log::warning('FreeEnrichmentService: GOOGLE_PLACES_KEY não configurada.');
        }
    }

    /**
     * Busca empresas por CNAE ao redor de lat/lng.
     * Aplica fallback de raio (ex.: 1.5x) até atingir targetMinResults ou bater no maxRadius.
     */
    public function buscarPorCnae(float $lat, float $lng, string $cnae, ?int $radius = null, array $internosParaExcluir = []): array
    {
        $radius = $radius ? max($this->minRadius, $radius) : $this->minRadius;
        $keyword = $this->mapearCnaeParaPalavraChave($cnae);

        Log::info('FreeEnrichmentService: início da busca', [
            'lat' => $lat,
            'lng' => $lng,
            'cnae' => $cnae,
            'keyword' => $keyword,
            'radius_start' => $radius,
            'live' => $this->live,
        ]);

        $coletados = [];
        $tentativas = 0;

        // Vamos expandindo o raio até atingir meta mínima ou travar no maxRadius
        while (true) {
            $tentativas++;

            $lote = $this->buscarPlacesNearby($lat, $lng, $radius, $keyword);
            $coletados = $this->mergeUniquePlaces($coletados, $lote);

            Log::info('FreeEnrichmentService: tentativa concluída', [
                'tentativa' => $tentativas,
                'radius' => $radius,
                'coletados_total' => count($coletados),
            ]);

            if (count($coletados) >= $this->targetMinResults || $radius >= $this->maxRadius) {
                break;
            }

            // Aumenta o raio (exponencial suave)
            $radius = (int) min($this->maxRadius, ceil($radius * 1.5));
        }

        // Remove duplicados em relação aos internos (nome/“fuzzy” e, se tiver, CNPJ)
        if (!empty($internosParaExcluir)) {
            $coletados = $this->filtrarContraInternos($coletados, $internosParaExcluir);
        }

        Log::info('FreeEnrichmentService: total após filtro/dup', [
            'resultado' => count($coletados),
        ]);

        return $coletados;
    }

    /**
     * Faz a chamada ao Nearby Search com paginação por next_page_token.
     * Usa cache quando live=false ou TTL definido.
     */
    protected function buscarPlacesNearby(float $lat, float $lng, int $radius, string $keyword): array
    {
        $cacheKey = "places:{$keyword}:{$lat},{$lng}:{$radius}";
        if (!$this->live && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey, []);
            Log::info('FreeEnrichmentService: respondendo do cache', ['cache_key' => $cacheKey, 'qtd' => count($cached)]);
            return $cached;
        }

        $acc = [];
        $params = [
            'location' => sprintf('%f,%f', $lat, $lng),
            'radius'   => $radius,
            'keyword'  => $keyword,
            'key'      => $this->apiKey,
            'opennow'  => 'false',
        ];
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

        $page = 0;
        $nextPageToken = null;

        do {
            $page++;
            $query = $params;
            if ($nextPageToken) {
                $query['pagetoken'] = $nextPageToken;
            }

            $resp = Http::retry(2, 200)
                ->timeout(15)
                ->get($url, $query);

            if (!$resp->ok()) {
                Log::warning('FreeEnrichmentService: falha HTTP no Nearby', [
                    'status' => $resp->status(),
                    'body' => Str::limit($resp->body(), 300),
                    'page' => $page,
                ]);
                break;
            }

            $data = $resp->json();

            if (!isset($data['results'])) {
                Log::warning('FreeEnrichmentService: resposta sem results', ['page' => $page, 'data_keys' => array_keys($data ?? [])]);
                break;
            }

            foreach ($data['results'] as $r) {
                $acc[] = [
                    'place_id' => $r['place_id'] ?? null,
                    'nome'     => $r['name'] ?? null,
                    'endereco' => $r['vicinity'] ?? ($r['formatted_address'] ?? null),
                    'lat'      => data_get($r, 'geometry.location.lat'),
                    'lng'      => data_get($r, 'geometry.location.lng'),
                    'rating'   => $r['rating'] ?? null,
                    'types'    => $r['types'] ?? [],
                    'raw'      => $r,
                ];
            }

            $nextPageToken = $data['next_page_token'] ?? null;
            if ($nextPageToken) {
                // A API do Places pede ~2s antes de usar o token
                usleep(2_200_000);
            }
        } while ($nextPageToken && $page < 3);

        // Normaliza e remove duplicados internos
        $acc = $this->dedupByPlaceIdOrName($acc);

        // Cacheia (mesmo em live=true é útil ter TTL curto; mas seguimos a flag)
        if (!$this->live) {
            Cache::put($cacheKey, $acc, now()->addHours($this->ttlHours));
        }

        return $acc;
    }

    protected function dedupByPlaceIdOrName(array $items): array
    {
        $seenPlace = [];
        $seenName  = [];

        $out = [];
        foreach ($items as $p) {
            $pid = $p['place_id'] ?? null;
            $nrm = $this->normalize($p['nome'] ?? '');

            if ($pid && isset($seenPlace[$pid])) {
                continue;
            }
            if ($nrm && isset($seenName[$nrm])) {
                // mesma empresa com place_id diferente
                continue;
            }
            if ($pid) $seenPlace[$pid] = true;
            if ($nrm) $seenName[$nrm]  = true;
            $out[] = $p;
        }
        return $out;
    }

    protected function mergeUniquePlaces(array $base, array $novo): array
    {
        if (empty($base)) return $novo;
        $idxPid = [];
        $idxName = [];
        foreach ($base as $b) {
            if (!empty($b['place_id'])) $idxPid[$b['place_id']] = true;
            if (!empty($b['nome'])) $idxName[$this->normalize($b['nome'])] = true;
        }
        foreach ($novo as $p) {
            $pid = $p['place_id'] ?? null;
            $nrm = $this->normalize($p['nome'] ?? '');
            if (($pid && isset($idxPid[$pid])) || ($nrm && isset($idxName[$nrm]))) {
                continue;
            }
            $base[] = $p;
        }
        return $base;
    }

    protected function filtrarContraInternos(array $externos, array $internos): array
    {
        // $internos: [['cnpj' => ?, 'nome' => ?, 'lat' => ?, 'lng' => ?], ...]
        $idxCnpj = collect($internos)
            ->pluck('cnpj')
            ->filter()
            ->map(fn($c) => preg_replace('/\D/', '', $c))
            ->flip()
            ->all();

        $nomesInternos = collect($internos)
            ->pluck('nome')
            ->filter()
            ->map(fn($n) => $this->normalize($n))
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($externos as $e) {
            $nomeN = $this->normalize($e['nome'] ?? '');
            $cnpj  = null; // Google Places normalmente não traz CNPJ
            $dupByCnpj = $cnpj ? isset($idxCnpj[$cnpj]) : false;

            $dupByName = false;
            foreach ($nomesInternos as $ni) {
                if ($this->similar($nomeN, $ni) >= 0.82) {
                    $dupByName = true; break;
                }
            }

            if ($dupByCnpj || $dupByName) {
                continue;
            }
            $out[] = $e;
        }
        return $out;
    }

    protected function normalize(?string $s): string
    {
        $s = mb_strtolower($s ?? '');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s;
    }

    /**
     * Similaridade rápida (Jaro-Winkler aproximado usando percent de letras em comum + posição)
     * Simples o suficiente para não depender de extensões.
     */
    protected function similar(string $a, string $b): float
    {
        if ($a === $b) return 1.0;
        similar_text($a, $b, $pct);
        // retorna 0..1
        return max(0.0, min(1.0, $pct / 100.0));
    }

    protected function mapearCnaeParaPalavraChave(string $cnae): string
    {
        $cnae = preg_replace('/\D/', '', $cnae);

        // 1) tenta cnae_catalog (se existir)
        try {
            $desc = DB::connection('sqlite')
                ->table('cnae_catalog')
                ->where('cnae', $cnae)
                ->value('descricao');

            if ($desc) {
                return $desc;
            }
        } catch (\Throwable $e) {
            // silencioso: tabela pode não existir
        }

        // 2) fallback por prefixo simples (exemplos)
        $map = [
            '4321500' => 'instalação de painéis solares|energia solar|solar installer',
            '4759899' => 'loja de cosméticos|perfumaria|maquiagem',
            '4711302' => 'supermercado|mercado|grocery',
        ];

        if (isset($map[$cnae])) {
            return $map[$cnae];
        }

        // 3) fallback final: usa o próprio CNAE como palavra (menos eficaz, mas melhor do que nada)
        return "empresa {$cnae}";
    }
}
