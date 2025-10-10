<?php

namespace App\Repositories\Prospect;


use App\Models\Prospect\CnaeCatalog;


class CnaeCatalogRepository
{
    public function getDescricao(string $codigo): ?string
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        $codigo = substr($codigo, 0, 7);
        return optional(CnaeCatalog::find($codigo))->descricao;
    }
}
