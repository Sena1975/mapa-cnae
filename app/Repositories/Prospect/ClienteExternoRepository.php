<?php

namespace App\Repositories\Prospect;


use App\Models\Prospect\ClienteExterno;
use Illuminate\Support\Facades\DB;


class ClienteExternoRepository
{
    public function insertOrSkip(array $data): ?ClienteExterno
    {
        return DB::connection('sqlite_prospect')->transaction(function () use ($data) {
            $exists = ClienteExterno::where('cnpj', $data['cnpj'])->first();
            if ($exists) return null;
            return ClienteExterno::create($data);
        });
    }
}
