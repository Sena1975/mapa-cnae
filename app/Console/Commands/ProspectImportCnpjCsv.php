<?php
namespace App\Console\Commands;

use App\Models\Prospect\CnpjRefEstabelecimento;
use Illuminate\Console\Command;

class ProspectImportCnpjCsv extends Command
{
    protected $signature = 'prospect:import-cnpj-csv 
        {--file= : Caminho do CSV}
        {--delimiter=; : Delimitador (; ou ,)}
        {--limit=0 : Limite de linhas (0 = tudo)}
        {--cidade= : Filtro de cidade (opcional)}
        {--uf= : Filtro UF (opcional)}';

    protected $description = 'Importa CNPJs candidatos (csv) para cnpj_ref_estabelecimento (SQLite).';

    public function handle(): int
    {
        $file = (string)$this->option('file');
        if (!$file || !file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");
            return self::FAILURE;
        }
        $delim = $this->option('delimiter') ?: ';';
        $limit = (int)$this->option('limit');
        $filtroCidade = $this->option('cidade');
        $filtroUf = $this->option('uf');

        $h = fopen($file, 'r');
        if (!$h) { $this->error("Não consegui abrir {$file}"); return self::FAILURE; }

        // Header esperado:
        // cnpj;razao_social;nome_fantasia;cnae_principal;logradouro;numero;bairro;municipio;uf;cep;situacao_cadastral
        $header = fgetcsv($h, 0, $delim);
        $map = array_flip(array_map(fn($s)=>mb_strtolower(trim($s ?? '')), $header ?: []));
        $get = function(array $row, string $col) use ($map) { return $row[$map[$col]] ?? null; };

        $lin = 0; $ok = 0; $skip = 0;
        while (($row = fgetcsv($h, 0, $delim)) !== false) {
            $lin++;
            $cnpj = preg_replace('/\D/', '', (string)$get($row,'cnpj'));
            if (strlen($cnpj) !== 14) { $skip++; continue; }

            $municipio = trim((string)$get($row,'municipio'));
            $uf = strtoupper(trim((string)$get($row,'uf')));
            if ($filtroCidade && strcasecmp($municipio, $filtroCidade) !== 0) { $skip++; continue; }
            if ($filtroUf && strcasecmp($uf, $filtroUf) !== 0) { $skip++; continue; }

            $cnae = substr(preg_replace('/\D/', '', (string)$get($row,'cnae_principal')), 0, 7);
            if (!$cnae) { $skip++; continue; }

            CnpjRefEstabelecimento::on('sqlite_prospect')->updateOrCreate(
                ['cnpj' => $cnpj],
                [
                    'razao_social' => $get($row,'razao_social'),
                    'nome_fantasia' => $get($row,'nome_fantasia'),
                    'cnae_principal' => $cnae,
                    'logradouro' => $get($row,'logradouro'),
                    'numero' => $get($row,'numero'),
                    'bairro' => $get($row,'bairro'),
                    'municipio' => $municipio,
                    'uf' => $uf,
                    'cep' => preg_replace('/\D/','',(string)$get($row,'cep')),
                    'situacao_cadastral' => $get($row,'situacao_cadastral') ?: 'ATIVA',
                ]
            );
            $ok++;
            if ($limit > 0 && $ok >= $limit) break;
        }
        fclose($h);

        $this->info("Importados: {$ok} | Ignorados: {$skip}");
        return self::SUCCESS;
    }
}
