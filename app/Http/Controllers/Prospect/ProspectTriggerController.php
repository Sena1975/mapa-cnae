<?php
namespace App\Http\Controllers\Prospect;

use App\Jobs\ProspectExternalClientsJob;
use App\Repositories\Prospect\InternalClientRepository;
use Illuminate\Http\Request;

class ProspectTriggerController
{
    public function dispatch(Request $req, InternalClientRepository $internal)
    {
        $data = $req->validate([
            'bounds.n' => 'required|numeric',
            'bounds.s' => 'required|numeric',
            'bounds.e' => 'required|numeric',
            'bounds.w' => 'required|numeric',
            'cnaes'    => 'required|array|min:1',
            'cnaes.*'  => 'string',
            'cidade'   => 'nullable|string',
            'uf'       => 'nullable|string|size:2',
        ]);

        // normaliza CNAEs (7 dígitos)
        $cnaes = array_values(array_unique(array_map(fn($c)=>substr(preg_replace('/\D/','',(string)$c),0,7), $data['cnaes'])));
        if (!$cnaes) return response()->json(['ok'=>false,'message'=>'Nenhum CNAE válido'], 422);

        // CNPJs já existentes na sua base (para EXCLUIR do job)
        $exclude = $internal->listCnpjsByAreaAndCnaes($cnaes, $data['cidade'] ?? null, $data['uf'] ?? null);

        // dispara job com a lista de exclusão
        dispatch(new ProspectExternalClientsJob(
            $data['bounds'], $cnaes, $data['cidade'] ?? null, $data['uf'] ?? null, $exclude
        ));

        return response()->json(['ok' => true, 'excluir' => count($exclude)]);
    }
}
