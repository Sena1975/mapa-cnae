<?php
namespace App\Repositories\Prospect;

use Illuminate\Support\Facades\DB;

class InternalClientRepository
{
    /**
     * Retorna CNPJs já existentes na sua base (Oracle ou default) para os CNAEs e área informados.
     * Ajuste 'clientes' e os nomes das colunas conforme seu schema real.
     */
    public function listCnpjsByAreaAndCnaes(array $cnaes, ?string $cidade, ?string $uf, int $limit = 5000): array
    {
        $cnaes = array_values(array_unique(array_map(fn($c)=>substr(preg_replace('/\D/','',$c),0,7), $cnaes)));
        if (!$cnaes) return [];

        $q = DB::table('clientes')->select('cnpj')
            ->whereIn(DB::raw("substr(regexp_replace(codigo_cnae, '[^0-9]', ''), 1, 7)"), $cnaes);

        if ($uf)     $q->where('uf', $uf);
        if ($cidade) $q->where('cidade', $cidade);

        // Evita coletar CNPJs inválidos
        $rows = $q->limit($limit)->get();
        $out  = [];
        foreach ($rows as $r) {
            $cnpj = preg_replace('/\D/', '', (string)($r->cnpj ?? ''));
            if (strlen($cnpj) === 14) $out[] = $cnpj;
        }
        return array_values(array_unique($out));
    }
}
