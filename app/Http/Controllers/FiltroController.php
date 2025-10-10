<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiltroController extends Controller
{
    protected function resolveFrom(string $table): string
    {
        $schema = env('ORACLE_SCHEMA');
        if ($schema) return $schema.'.'.$table;

        try {
            $row = DB::connection('oracle')->selectOne(
                "SELECT TABLE_OWNER, TABLE_NAME
                   FROM ALL_SYNONYMS
                  WHERE (OWNER = USER OR OWNER = 'PUBLIC')
                    AND SYNONYM_NAME = UPPER(?)",
                [$table]
            );
            if ($row && isset($row->TABLE_OWNER, $row->TABLE_NAME)) {
                return $row->TABLE_OWNER.'.'.$row->TABLE_NAME;
            }
        } catch (\Throwable $e) {}
        return $table;
    }

    protected function distinctLike(string $column, Request $request)
    {
        $table = env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA');
        $from  = $this->resolveFrom($table);

        $q     = trim((string)$request->query('q', ''));
        $limit = max(10, min((int)$request->query('limit', 25), 100));

        $builder = DB::connection('oracle')->table(DB::raw($from));
        if ($q !== '') {
            $builder->whereRaw("UPPER($column) LIKE UPPER(?)", ["%{$q}%"]);
        }

        $rows = $builder->select(DB::raw("DISTINCT $column AS value"))
                        ->orderBy(DB::raw($column), 'asc')
                        ->limit($limit)
                        ->get();

        return response()->json($rows->pluck('value')->filter()->values());
    }

    public function cidades(Request $request)   { return $this->distinctLike('CIDADE',          $request); }
    public function cnaes(Request $request)     { return $this->distinctLike('CNAE',            $request); }
    public function equipes(Request $request)   { return $this->distinctLike('SUPERVISOR',      $request); }
    public function vendedores(Request $request){ return $this->distinctLike('VENDEDOR',        $request); }
    public function ramos(Request $request)     { return $this->distinctLike('RAMO_ATIVIDADE',  $request); }
}
