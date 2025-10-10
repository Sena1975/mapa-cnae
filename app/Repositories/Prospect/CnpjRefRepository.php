<?php

namespace App\Repositories\Prospect;


use App\Models\Prospect\CnpjRefEstabelecimento;


class CnpjRefRepository
{
    /**
     * Busca candidatos por CNAEs e área grosseira (cidade/UF). O refinamento por bounds será feito após geocodificação.
     */
    public function findByCnaesAndArea(array $cnaes, ?string $cidade, ?string $uf, int $limit = 500): \Illuminate\Support\Collection
    {
        $q = CnpjRefEstabelecimento::query()->whereIn('cnae_principal', $cnaes);
        if ($uf) $q->where('uf', $uf);
        if ($cidade) $q->where('municipio', $cidade);
        $q->where(function ($w) {
            $w->whereNull('situacao_cadastral')->orWhere('situacao_cadastral', 'ATIVA');
        });
        return $q->limit($limit)->get();
    }
}
