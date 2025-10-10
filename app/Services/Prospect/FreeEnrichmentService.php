<?php

namespace App\Services\Prospect;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FreeEnrichmentService
{
    public function fetchByCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) return [];


        return Cache::remember("brasilapi:cnpj:$cnpj", 86400, function () use ($cnpj) {
            $res = Http::timeout(15)->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
            if (!$res->ok()) return [];
            return $res->json();
        });
    }


    public function completeIbgeByCep(?string $cep): ?string
    {
        $cep = preg_replace('/\D/', '', (string)$cep);
        if (!$cep || strlen($cep) !== 8) return null;


        $data = Cache::remember("viacep:$cep", 2592000, function () use ($cep) {
            $res = Http::timeout(15)->get("https://viacep.com.br/ws/{$cep}/json");
            if (!$res->ok()) return null;
            return $res->json();
        });
        return $data['ibge'] ?? null;
    }


    public function geocodeNominatim(string $q): ?array
    {
        // Rate limit simples: 1 req/s global
        $key = 'nominatim:last';
        $last = Cache::get($key);
        if ($last) {
            $diff = microtime(true) - (float)$last;
            if ($diff < 1.0) usleep((int)((1.0 - $diff) * 1_000_000));
        }


        $ua = config('services.nominatim.user_agent', env('NOMINATIM_USER_AGENT', 'Mapa-CNAE/1.0'));
        $url = 'https://nominatim.openstreetmap.org/search';
        $cached = Cache::remember("nominatim:" . md5($q), 7776000, function () use ($url, $q, $ua) {
            $res = Http::withHeaders(['User-Agent' => $ua])
                ->timeout(20)
                ->get($url, [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => 1,
                ]);
            if (!$res->ok()) return null;
            $js = $res->json();
            return $js[0] ?? null;
        });
        Cache::put($key, microtime(true));
        if (!$cached) return null;
        return [
            'lat' => isset($cached['lat']) ? (float)$cached['lat'] : null,
            'lon' => isset($cached['lon']) ? (float)$cached['lon'] : null,
        ];
    }
}
