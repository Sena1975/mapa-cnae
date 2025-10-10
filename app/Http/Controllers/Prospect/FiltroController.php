<?php
namespace App\Http\Controllers\Prospect;

use App\Models\Prospect\CnaeCatalog;
use Illuminate\Http\Request;

class FiltroController
{
    public function cnaes(Request $req)
    {
        $q = trim((string)$req->query('q', ''));
        $limit = min(max((int)$req->query('limit', 25), 1), 50);

        $sql = CnaeCatalog::on('sqlite_prospect')->select('codigo','descricao')->orderBy('codigo');

        if ($q !== '') {
            $qNum = substr(preg_replace('/\D/','', $q), 0, 7);
            $sql->where(function($w) use ($q, $qNum) {
                if ($qNum) $w->orWhere('codigo', 'like', $qNum.'%');
                $w->orWhere('descricao', 'like', '%'.$q.'%');
            });
        }

        $rows = $sql->limit($limit)->get();
        // devolve como “1234567 - Descrição”
        return response()->json($rows->map(fn($r)=> "{$r->codigo} - {$r->descricao}"));
    }
}
