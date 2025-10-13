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
        $this->apiKey           = (string) env('GOOGLE_PLACES_KEY', '');
        $this->live             = filter_var(env('PLACES_LIVE', true), FILTER_VALIDATE_BOOLEAN);
        $this->ttlHours         = (int) env('PLACES_CACHE_TTL_HOURS', 168);
        $this->maxRadius        = (int) env('PLACES_MAX_RADIUS_M', 15000);
        $this->minRadius        = max(1000, (int) env('PLACES_MIN_RADIUS_M', 1500));
        $this->targetMinResults = (int) env('PLACES_TARGET_MIN_RESULTS', 8);

        if (empty($this->apiKey)) {
            Log::warning('FreeEnrichmentService: GOOGLE_PLACES_KEY não configurada.');
        }
    }

    public function buscarPorCnae(float $lat, float $lng, string $cnae, ?int $radius = null, array $internosParaExcluir = []): array
    {
        $radius  = $radius ? max($this->minRadius, $radius) : $this->minRadius;
        $keyword = $this->mapearCnaeParaPalavraChave($cnae);

        Log::info('FreeEnrichmentService: início da busca', compact('lat','lng','cnae','keyword','radius') + ['live'=>$this->live]);

        $coletados = [];
        $tentativas = 0;

        while (true) {
            $tentativas++;

            $lote = $this->buscarPlacesNearby($lat, $lng, $radius, $keyword);
            $coletados = $this->mergeUniquePlaces($coletados, $lote);

            Log::info('FreeEnrichmentService: tentativa', ['tentativa'=>$tentativas,'radius'=>$radius,'coletados'=>count($coletados)]);

            if (count($coletados) >= $this->targetMinResults || $radius >= $this->maxRadius) break;

            $radius = (int) min($this->maxRadius, ceil($radius * 1.5));
        }

        if (!empty($internosParaExcluir)) {
            $coletados = $this->filtrarContraInternos($coletados, $internosParaExcluir);
        }

        Log::info('FreeEnrichmentService: total final', ['qtd'=>count($coletados)]);
        return $coletados;
    }

    protected function buscarPlacesNearby(float $lat, float $lng, int $radius, string $keyword): array
    {
        $cacheKey = "places:{$keyword}:{$lat},{$lng}:{$radius}";
        if (!$this->live && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey, []);
            Log::info('FreeEnrichmentService: respondendo do cache', ['cache_key'=>$cacheKey,'qtd'=>count($cached)]);
            return $cached;
        }

        $acc = [];
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
        $params = [
            'location' => sprintf('%f,%f', $lat, $lng),
            'radius'   => $radius,
            'keyword'  => $keyword,
            'key'      => $this->apiKey,
        ];

        $next = null; $page = 0;
        do {
            $page++;
            $query = $params;
            if ($next) $query['pagetoken'] = $next;

            $resp = Http::retry(2, 200)->timeout(15)->get($url, $query);
            if (!$resp->ok()) {
                Log::warning('NearbySearch falhou', ['status'=>$resp->status(),'body'=>Str::limit($resp->body(),300),'page'=>$page]);
                break;
            }

            $data = $resp->json();
            foreach (($data['results'] ?? []) as $r) {
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

            $next = $data['next_page_token'] ?? null;
            if ($next) usleep(2_200_000);
        } while ($next && $page < 3);

        $acc = $this->dedupByPlaceIdOrName($acc);

        if (!$this->live) Cache::put($cacheKey, $acc, now()->addHours($this->ttlHours));
        return $acc;
    }

    protected function dedupByPlaceIdOrName(array $items): array
    {
        $seenPlace = $seenName = []; $out = [];
        foreach ($items as $p) {
            $pid = $p['place_id'] ?? null;
            $nrm = $this->normalize($p['nome'] ?? '');
            if (($pid && isset($seenPlace[$pid])) || ($nrm && isset($seenName[$nrm]))) continue;
            if ($pid) $seenPlace[$pid] = true;
            if ($nrm) $seenName[$nrm] = true;
            $out[] = $p;
        }
        return $out;
    }

    protected function mergeUniquePlaces(array $base, array $novo): array
    {
        if (empty($base)) return $novo;
        $idxPid = $idxName = [];
        foreach ($base as $b) {
            if (!empty($b['place_id'])) $idxPid[$b['place_id']] = true;
            if (!empty($b['nome'])) $idxName[$this->normalize($b['nome'])] = true;
        }
        foreach ($novo as $p) {
            $pid = $p['place_id'] ?? null;
            $nrm = $this->normalize($p['nome'] ?? '');
            if (($pid && isset($idxPid[$pid])) || ($nrm && isset($idxName[$nrm]))) continue;
            $base[] = $p;
        }
        return $base;
    }

    protected function filtrarContraInternos(array $externos, array $internos): array
    {
        $nomesInternos = collect($internos)->pluck('nome')->filter()->map(fn($n) => $this->normalize($n))->unique()->values()->all();
        $out = [];
        foreach ($externos as $e) {
            $nomeN = $this->normalize($e['nome'] ?? '');
            $dup = false;
            foreach ($nomesInternos as $ni) {
                if ($this->similar($nomeN, $ni) >= 0.82) { $dup = true; break; }
            }
            if (!$dup) $out[] = $e;
        }
        return $out;
    }

    protected function normalize(?string $s): string
    {
        $s = mb_strtolower($s ?? '');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        return preg_replace('/\s+/', ' ', trim($s));
    }

    protected function similar(string $a, string $b): float
    {
        if ($a === $b) return 1.0;
        similar_text($a, $b, $pct);
        return max(0.0, min(1.0, $pct / 100.0));
    }

    protected function mapearCnaeParaPalavraChave(string $cnae): string
    {
        $cnae = preg_replace('/\D/', '', $cnae);

        try {
            $desc = DB::connection('sqlite')
                ->table('cnae_catalog')
                ->where('cnae', $cnae)
                ->value('descricao');
            if ($desc) return $desc;
        } catch (\Throwable $e) { /* ok */ }

        $map = [
            '4321500' => 'instalação de painéis solares|energia solar|solar installer',
            '4759899' => 'loja de cosméticos|perfumaria|maquiagem',
            '4711302' => 'supermercado|mercado|grocery',
        ];
        if (isset($map[$cnae])) return $map[$cnae];

        return "empresa {$cnae}";
    }
}
