<?php

namespace App\Http\Controllers\Prospect;


use App\Models\Prospect\ClienteExterno;
use App\Support\GeoBounds;
use Illuminate\Http\Request;


class ClienteExternoController
{
public function countInBounds(Request $req)
{
    // se all=1, conta geral sem bounds (para debug rápido)
    if ($req->boolean('all')) {
        $c = ClienteExterno::on('sqlite_prospect')->count();
        return response()->json(['count' => $c]);
    }

    $b = $this->parseBounds($req);
    if (!$b) return response()->json(['count' => 0]);

    $count = ClienteExterno::on('sqlite_prospect')
        ->whereBetween('latitude', [$b['s'], $b['n']])
        ->whereBetween('longitude', [$b['w'], $b['e']])
        ->count();

    return response()->json(['count' => $count]);
}

public function listInBounds(Request $req)
{
    $all = $req->boolean('all');                      // << NOVO
    $b = $this->parseBounds($req);
    $per = min(max((int)$req->query('per_page', 100), 1), 500);

    $q = ClienteExterno::on('sqlite_prospect');

    if (!$all && $b) {                                // << só filtra por bounds se !all
        $q->whereBetween('latitude', [$b['s'], $b['n']])
          ->whereBetween('longitude', [$b['w'], $b['e']]);
    }

    if ($req->filled('cnae')) {
        $q->where('codigo_cnae', substr(preg_replace('/\D/','', $req->query('cnae')), 0, 7));
    }

    return response()->json(
        $q->orderByDesc('enriched_at')->paginate($per)
    );
}


    private function parseBounds($req): ?array
    {
        $bounds = $req->query('bounds');
        if (is_string($bounds)) $bounds = json_decode($bounds, true);
        if (!is_array($bounds) || !isset($bounds['n'], $bounds['s'], $bounds['e'], $bounds['w'])) return null;
        return $bounds;
    }
}
