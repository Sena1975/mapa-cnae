<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientesExport implements FromCollection, WithHeadings
{
    protected $filters;
    protected $includeExternals;

    public function __construct(array $filters = [], bool $includeExternals = false)
    {
        $this->filters = $filters;
        $this->includeExternals = $includeExternals;
    }

    protected function resolveFrom(string $table): string
    {
        $schema = env('ORACLE_SCHEMA');
        if ($schema) {
            return $schema . '.' . $table;
        }
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
        } catch (\Throwable $e) {
            // segue com o nome simples
        }
        return $table;
    }

    public function collection()
    {
        $table  = env('ORACLE_TABLE', 'VIEW_APP_CLIENTE_MAPA');
        $from   = $this->resolveFrom($table);

        $query = DB::connection('oracle')->table(DB::raw($from));

        // filtros
        if (!empty($this->filters['cidade']))  {$query->whereRaw('UPPER(CIDADE) LIKE UPPER(?)', ['%'.$this->filters['cidade'].'%']);}
        if (!empty($this->filters['cnae']))     $query->where('CNAE', $this->filters['cnae']);
        if (!empty($this->filters['equipe']))   $query->where('SUPERVISOR', $this->filters['equipe']);
        if (!empty($this->filters['vendedor'])) $query->where('VENDEDOR', $this->filters['vendedor']);
        if (!empty($this->filters['ramo']))     $query->where('RAMO_ATIVIDADE', $this->filters['ramo']);

        $nomeExpr = DB::raw('COALESCE(NOME_FANTASIA, RAZAO_SOCIAL) as nome_exibicao');

        $internos = $query->select([
            'CODIGO_CLIENTE as codigo_cliente',
            'RAZAO_SOCIAL as razao_social',
            'NOME_FANTASIA as nome_fantasia',
            'CNPJ as cnpj',
            'INSCRICAO_ESTADUAL as inscricao_estadual',
            'CNAE as cnae',
            'RAMO_ATIVIDADE as ramo_atividade',
            'PRACA_ENTREGA as praca_entrega',
            'VENDEDOR as vendedor',
            'SUPERVISOR as supervisor',
            'LIMITE_CREDITO as limite_credito',
            'FATURAMENTO_MEDIO as faturamento_medio',
            'VALOR_MAIOR_FATURAMENTO as valor_maior_faturamento',
            'MIX_PRODUTOS as mix_produtos',
            'DATA_CADASTRO as data_cadastro',
            'DATA_ULTIMA_COMPRA as data_ultima_compra',
            'LATITUDE as latitude',
            'LONGITUDE as longitude',
            $nomeExpr,
        ])->get()->map(function ($r) {
            $nome = $r->nome_exibicao ?? $r->nome_fantasia ?? $r->razao_social ?? '—';
            return [
                'Origem'               => 'interno',
                'Código Cliente'       => $r->codigo_cliente,
                'Nome'                 => $nome,
                'CNPJ'                 => $r->cnpj,
                'IE'                   => $r->inscricao_estadual,
                'CNAE'                 => $r->cnae,
                'Ramo Atividade'       => $r->ramo_atividade,
                'Praça'                => $r->praca_entrega,
                'Vendedor'             => $r->vendedor,
                'Equipe (Supervisor)'  => $r->supervisor,
                'Limite Crédito'       => $r->limite_credito,
                'Faturamento Médio'    => $r->faturamento_medio,
                'Valor Maior Fatur.'   => $r->valor_maior_faturamento,
                'Mix Produtos'         => $r->mix_produtos,
                'Data Cadastro'        => $r->data_cadastro ? date('Y-m-d', strtotime($r->data_cadastro)) : null,
                'Data Últ. Compra'     => $r->data_ultima_compra ? date('Y-m-d', strtotime($r->data_ultima_compra)) : null,
                'Latitude'             => $r->latitude,
                'Longitude'            => $r->longitude,
            ];
        });

        $externos = collect();
        if ($this->includeExternals) {
            $externos = collect([
                [
                    'Origem'               => 'externo',
                    'Código Cliente'       => null,
                    'Nome'                 => 'Empresa Externa 1',
                    'CNPJ'                 => null,
                    'IE'                   => null,
                    'CNAE'                 => $this->filters['cnae'] ?? null,
                    'Ramo Atividade'       => $this->filters['ramo'] ?? null,
                    'Praça'                => $this->filters['praca'] ?? null,
                    'Vendedor'             => null,
                    'Equipe (Supervisor)'  => null,
                    'Limite Crédito'       => null,
                    'Faturamento Médio'    => null,
                    'Valor Maior Fatur.'   => null,
                    'Mix Produtos'         => null,
                    'Data Cadastro'        => null,
                    'Data Últ. Compra'     => null,
                    'Latitude'             => -12.9714,
                    'Longitude'            => -38.5014,
                ],
            ]);
        }

        return $internos->concat($externos);
    }

    public function headings(): array
    {
        return [
            'Origem','Código Cliente','Nome','CNPJ','IE','CNAE','Ramo Atividade','Praça',
            'Vendedor','Equipe (Supervisor)','Limite Crédito','Faturamento Médio','Valor Maior Fatur.',
            'Mix Produtos','Data Cadastro','Data Últ. Compra','Latitude','Longitude'
        ];
    }
}
