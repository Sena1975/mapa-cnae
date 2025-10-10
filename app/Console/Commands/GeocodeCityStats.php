<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeCityStats extends Command
{
    protected $signature = 'geocode:city-stats {--cidade=} {--limit=5000}';
    protected $description = 'Estatísticas de geocoding por cidade (conferindo a view Oracle × cache SQLite)';

    public function handle()
    {
        $cidade = trim((string)$this->option('cidade'));
        $limit  = (int)$this->option('limit');

        if ($cidade === '') {
            $this->error('Use --cidade="NOME DA CIDADE"');
            return self::INVALID;
        }

        $from = $this->resolveFrom(env('ORACLE_TABLE','VIEW_APP_CLIENTE_MAPA'));
        $rows = DB::connection('oracle')->table(DB::raw($from))
            ->select([
                DB::raw('ENDERECO as endereco'),
                DB::raw('NUMERO as numero'),
                DB::raw('BAIRRO as bairro'),
                DB::raw('CIDADE as cidade'),
                DB::raw('UF as uf'),
                DB::raw('CEP as cep'),
            ])
            ->whereRaw('UPPER(CIDADE) LIKE UPPER(?)', ["%{$cidade}%"])
            ->limit($limit)
            ->get();

        $ok=$miss=0; $samplesMissing = [];

        foreach ($rows as $r) {
            $full = $this->buildFullAddress($r);
            if (!$full) { $miss++; continue; }

            $hash = hash('sha256', mb_strtolower(trim(preg_replace('/\s+/', ' ', $full)), 'UTF-8'));
            $exists = DB::connection('sqlite')->table('geocode_cache')
                ->where('addr_hash', $hash)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->exists();

            if ($exists) $ok++; else {
                $miss++;
                if (count($samplesMissing) < 10) $samplesMissing[] = [$full];
            }
        }

        $this->table(['Cidade','Total (amostra)','Com coord.','Sem coord.'],
            [[ strtoupper($cidade), $rows->count(), $ok, $miss ]]);

        if ($samplesMissing) {
            $this->info('Exemplos sem coordenadas (até 10):');
            $this->table(['Endereço'], $samplesMissing);
        }

        return self::SUCCESS;
    }

    protected function resolveFrom(string $table): string
    {
        $schema = env('ORACLE_SCHEMA');
        if ($schema) return $schema.'.'.$table;

        try {
            $row = DB::connection('oracle')->selectOne(
                "SELECT TABLE_OWNER, TABLE_NAME FROM ALL_SYNONYMS
                 WHERE (OWNER = USER OR OWNER = 'PUBLIC') AND SYNONYM_NAME = UPPER(?)",
                [$table]
            );
            if ($row && isset($row->TABLE_OWNER, $row->TABLE_NAME)) {
                return $row->TABLE_OWNER.'.'.$row->TABLE_NAME;
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
