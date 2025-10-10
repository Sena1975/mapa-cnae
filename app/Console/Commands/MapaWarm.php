<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\GeocodeCache;

/**
 * Pré-aquece o cache de geocoding em SQLite.
 *
 * Exemplos:
 *  php artisan mapa:warm --cidade="Lauro de Freitas" --limit=3000
 *  php artisan mapa:warm --cnae=5611201 --limit=5000
 *  php artisan mapa:warm --cidade="Salvador" --retry=2 --sleep=150
 */
class MapaWarm extends Command
{
    protected $signature = 'mapa:warm
        {--cidade= : Filtro de cidade (LIKE)}
        {--cnae= : Filtro de CNAE (exato)}
        {--limit=2000 : Limite de registros}
        {--retry=1 : Tentativas extras com variações do endereço}
        {--sleep=100 : Delay (ms) entre chamadas para evitar OVER_QUERY_LIMIT}
        {--dry-run : Não grava cache; apenas simula e mostra diagnóstico}';

    protected $description = 'Pré-aquecer cache de geocoding (SQLite) com diagnóstico e retries';

    public function handle()
    {
        // força geocoding live durante o warm, sem mexer no .env em produção
        putenv('GEOCODE_CACHE_ONLY=false');
        $_ENV['GEOCODE_CACHE_ONLY']    = 'false';
        $_SERVER['GEOCODE_CACHE_ONLY'] = 'false';

        $cidade = $this->option('cidade');
        $cnae   = $this->option('cnae');
        $limit  = (int)$this->option('limit');
        $retries = max(0, (int)$this->option('retry'));
        $sleepMs = max(0, (int)$this->option('sleep'));
        $dryRun  = (bool)$this->option('dry-run');

        $from = $this->resolveFrom(env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA'));

        $q = DB::connection('oracle')->table(DB::raw($from));
        if ($cidade) $q->whereRaw('UPPER(CIDADE) LIKE UPPER(?)', ["%{$cidade}%"]);
        if ($cnae)   $q->where('CNAE', $cnae);

        $rows = $q->select([
            DB::raw('ENDERECO as endereco'),
            DB::raw('NUMERO as numero'),
            DB::raw('BAIRRO as bairro'),
            DB::raw('CIDADE as cidade'),
            DB::raw('UF as uf'),
            DB::raw('CEP as cep'),
        ])->limit($limit)->get();

        $ok = $zero = $denied = $over = $other = 0;
        $processed = 0;

        $this->info("Iniciando warm: {$rows->count()} registros | retry={$retries} | sleep={$sleepMs}ms | dry-run=" . ($dryRun ? 'on' : 'off'));

        foreach ($rows as $r) {
            $processed++;
            $full = $this->buildFullAddress($r);
            if (!$full) {
                $zero++;
                $this->line("{$processed}/{$limit}  [SKIP] endereço insuficiente");
                continue;
            }

            $res = $this->geocodeWithRetries($full, $r, $retries, $sleepMs, $dryRun);

            switch ($res['status']) {
                case 'OK':                $ok++;    break;
                case 'ZERO_RESULTS':      $zero++;  break;
                case 'OVER_QUERY_LIMIT':  $over++;  break;
                case 'REQUEST_DENIED':    $denied++;break;
                default:                  $other++; break;
            }

            if ($processed % 50 === 0 || $res['status'] !== 'OK') {
                $this->line(sprintf(
                    "%d/%d  [%s]  %s",
                    $processed, $limit, str_pad($res['status'], 16), $res['short'] ?? ''
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Total', 'OK', 'ZERO_RESULTS', 'OVER_QUERY_LIMIT', 'REQUEST_DENIED', 'OTHER'],
            [[ $processed, $ok, $zero, $over, $denied, $other ]]
        );
        $this->info('Warm concluído.');
        return self::SUCCESS;
    }

    /* -------------------------------------------------------
     * Geocoding com retries e variações de endereço
     * ----------------------------------------------------- */
    protected function geocodeWithRetries(string $addr, object $r, int $retries, int $sleepMs, bool $dryRun): array
    {
        $variants = $this->addressVariants($addr, $r);

        $attempt = 0;
        foreach ($variants as $variant) {
            $attempt++;
            $resp = $this->geocodeLive($variant, $dryRun);

            // backoff simples para OVER_QUERY_LIMIT
            if ($resp['status'] === 'OVER_QUERY_LIMIT') {
                usleep(max(150000, $sleepMs * 1000)); // >=150ms
                continue; // tenta próxima variação (ou mesma, na prática)
            }

            if ($resp['status'] === 'OK' || $attempt > $retries + 1) {
                return $resp + ['short' => $this->shortAddr($variant)];
            }

            // ZERO_RESULTS ou outros -> tenta próxima variação
            if ($sleepMs > 0) usleep($sleepMs * 1000);
        }

        // se saiu do loop sem OK, retorna última resposta ou genérica
        return [
            'status' => 'ZERO_RESULTS',
            'lat' => null, 'lng' => null,
            'short' => $this->shortAddr($addr),
        ];
    }

    protected function geocodeLive(string $address, bool $dryRun): array
    {
        // cache key
        $hash = hash('sha256', mb_strtolower(trim(preg_replace('/\s+/', ' ', $address)), 'UTF-8'));
        $cached = GeocodeCache::where('addr_hash', $hash)->first();
        if ($cached && !$dryRun) {
            return [
                'status' => 'OK',
                'lat'    => $cached->lat !== null ? (float)$cached->lat : null,
                'lng'    => $cached->lng !== null ? (float)$cached->lng : null,
            ];
        }

        $apiKey = env('GOOGLE_GEOCODE_KEY', env('GOOGLE_MAPS_KEY'));
        if (!$apiKey) {
            return ['status' => 'REQUEST_DENIED', 'lat' => null, 'lng' => null];
        }

        try {
            $resp = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address'  => $address,
                'key'      => $apiKey,
                'language' => 'pt-BR',
                'region'   => 'br',
            ])->json();

            $status = $resp['status'] ?? 'UNKNOWN';
            if ($status === 'OK' && isset($resp['results'][0]['geometry']['location'])) {
                $res = $resp['results'][0];
                $lat = (float)($res['geometry']['location']['lat'] ?? null);
                $lng = (float)($res['geometry']['location']['lng'] ?? null);

                if (!$dryRun) {
                    GeocodeCache::updateOrCreate(
                        ['addr_hash' => $hash],
                        [
                            'input_address'     => $address,
                            'formatted_address' => $res['formatted_address'] ?? null,
                            'lat'               => $lat,
                            'lng'               => $lng,
                            'provider'          => 'google',
                        ]
                    );
                }

                return ['status' => 'OK', 'lat' => $lat, 'lng' => $lng];
            }

            // log detalhado para troubleshooting
            \Log::error('Warm geocode falhou', [
                'status' => $status,
                'error_message' => $resp['error_message'] ?? null,
                'address' => $address,
            ]);

            return ['status' => $status, 'lat' => null, 'lng' => null];

        } catch (\Throwable $e) {
            \Log::error('Warm geocode erro de rede', ['address' => $address, 'ex' => $e->getMessage()]);
            return ['status' => 'NETWORK', 'lat' => null, 'lng' => null];
        }
    }

    /** Variações do endereço para melhorar acerto */
    protected function addressVariants(string $full, object $r): array
    {
        $variants = [];
        $variants[] = $full;

        // Remove CEP
        if (!empty($r->cep)) {
            $variants[] = str_replace(', ' . $r->cep, '', $full);
        }

        // Sem número
        if (!empty($r->numero)) {
            $variants[] = preg_replace('/,\s*' . preg_quote((string)$r->numero, '/') . '(\s|,|$)/', ' ', $full);
        }

        // Sem bairro
        if (!empty($r->bairro)) {
            $withoutBairro = preg_replace('/\s-\s*' . preg_quote($r->bairro, '/') . '(\s|,|$)/i', ' ', $full);
            if ($withoutBairro !== $full) $variants[] = $withoutBairro;
        }

        // Apenas Cidade - UF (+ CEP se houver)
        $cidadeUf = trim(implode(' - ', array_filter([$r->cidade ?? null, $r->uf ?? null])));
        if ($cidadeUf) {
            $variants[] = $cidadeUf . (!empty($r->cep) ? ', ' . $r->cep : '') . ', Brasil';
        }

        // Endereço + Cidade (sem UF/CEP)
        if (!empty($r->endereco) && !empty($r->cidade)) {
            $variants[] = trim($r->endereco . ', ' . $r->cidade) . ', Brasil';
        }

        // Dedup e limpa
        $variants = array_values(array_unique(array_map(function ($s) {
            return trim(preg_replace('/\s+/', ' ', $s));
        }, $variants)));

        return $variants;
    }

    protected function shortAddr(string $address): string
    {
        return mb_strimwidth($address, 0, 80, '...');
    }

    /* -------- Oracle helpers -------- */

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

    protected function buildFullAddress(object $r): ?string
    {
        $addr = '';
        if (!empty($r->endereco)) $addr .= trim($r->endereco);
        if (!empty($r->numero))   $addr .= ', ' . trim($r->numero);
        if (!empty($r->bairro))   $addr .= ' - ' . trim($r->bairro);

        $cidadeUf = trim(implode(' - ', array_filter([$r->cidade ?? null, $r->uf ?? null])));
        if ($cidadeUf) $addr .= ', ' . $cidadeUf;
        if (!empty($r->cep)) $addr .= ', ' . trim($r->cep);

        $addr = trim(preg_replace('/\s+/', ' ', $addr));
        if (mb_strlen($addr) < 5) return null;

        return $addr . ', Brasil';
    }
}
