<?php
namespace App\Jobs;

use App\Models\Prospect\ClienteExterno;
use App\Repositories\Prospect\ClienteExternoRepository;
use App\Repositories\Prospect\CnaeCatalogRepository;
use App\Repositories\Prospect\CnpjRefRepository; // se não usar dataset local, pode remover
use App\Services\Prospect\FreeEnrichmentService;
use App\Support\GeoBounds;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class ProspectExternalClientsJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array{n:float,s:float,e:float,w:float} */
    public array $bounds;
    /** @var string[] */
    public array $cnaes;
    public ?string $cidade;
    public ?string $uf;
    

    /**
     * @param array{n:float,s:float,e:float,w:float} $bounds
     * @param string[] $cnaes
     */    
    public array $excludeCnpjs;

    public function __construct(array $bounds, array $cnaes, ?string $cidade = null, ?string $uf = null, array $excludeCnpjs = [])
    {
        $this->bounds = $bounds;
        $this->cnaes  = array_values(array_unique(array_map(fn($c)=>substr(preg_replace('/\D/','',$c),0,7), $cnaes)));
        $this->cidade = $cidade;
        $this->uf     = $uf;
        $this->excludeCnpjs = array_values(array_unique(array_filter(array_map(function($c){
            $c = preg_replace('/\D/','', (string)$c);
            return strlen($c)===14 ? $c : null;
        }, $excludeCnpjs))));
        $this->onQueue('prospect');
    }
    public function handle(
        ?CnpjRefRepository $refRepo = null,
        FreeEnrichmentService $svc,
        ClienteExternoRepository $repo,
        CnaeCatalogRepository $cnaeRepo
    ): void {
        // Conjunto para pular CNPJs que já são da sua base
        $skipSet = array_flip($this->excludeCnpjs);        
        // 1) Candidatos (via dataset local, se existir)
        $candidatos = collect();
        if ($refRepo) {
            $candidatos = $refRepo->findByCnaesAndArea($this->cnaes, $this->cidade, $this->uf, 500);
        }

        // 2) Processa cada candidato
        foreach ($candidatos as $cand) {
            $cnpj = preg_replace('/\D/', '', (string)($cand->cnpj ?? ''));
            if (strlen($cnpj) !== 14) {
                continue;
            }
 
         // Pule se já é cliente interno
            if (isset($skipSet[$cnpj])) continue;
            // já existe?
 
            if (ClienteExterno::on('sqlite_prospect')->where('cnpj', $cnpj)->exists()) {
                continue;
            }

            // Pule se já foi inserido como externo
            if (\App\Models\Prospect\ClienteExterno::on('sqlite_prospect')->where('cnpj', $cnpj)->exists()) {
                continue;
            }
            // base de endereço (dataset local)
            $logradouro = trim((string)($cand->logradouro ?? ''));
            $numero     = trim((string)($cand->numero ?? ''));
            $bairro     = trim((string)($cand->bairro ?? ''));
            $cidade     = trim((string)($cand->municipio ?? ''));
            $uf         = trim((string)($cand->uf ?? ''));
            $cep        = preg_replace('/\D/', '', (string)($cand->cep ?? ''));

            // completa via BrasilAPI quando faltar
            $cad = $svc->fetchByCnpj($cnpj);
            if ($cad) {
                $logradouro = $logradouro ?: ($cad['logradouro'] ?? null);
                $numero     = $numero ?: ($cad['numero'] ?? null);
                $bairro     = $bairro ?: ($cad['bairro'] ?? null);
                $cidade     = $cidade ?: ($cad['municipio'] ?? null);
                $uf         = $uf ?: ($cad['uf'] ?? null);
                $cep        = $cep ?: preg_replace('/\D/', '', (string)($cad['cep'] ?? ''));
            }

            // IBGE via CEP
            $ibge = $svc->completeIbgeByCep($cep);

            // Geocode (Nominatim)
            $addrParts = array_filter([$logradouro, $numero, $bairro, $cidade, $uf, $cep, 'Brasil']);
            $q = implode(', ', $addrParts);
            $geo = $q ? $svc->geocodeNominatim($q) : null;

            // valida bounds (se temos geo)
            if ($geo && !GeoBounds::contains($this->bounds, (float)$geo['lat'], (float)$geo['lon'])) {
                continue;
            }

            // descrição CNAE
            $cnae = substr(preg_replace('/\D/', '', (string)($cand->cnae_principal ?? '')), 0, 7);
            $desc = $cnae ? $cnaeRepo->getDescricao($cnae) : null;

            // Insere
            $repo->insertOrSkip([
                'cnpj'             => $cnpj,
                'razao_social'     => $cand->razao_social ?? ($cad['razao_social'] ?? null),
                'nome_fantasia'    => $cand->nome_fantasia ?? ($cad['nome_fantasia'] ?? null),
                'codigo_cnae'      => $cnae,
                'descricao_cnae'   => $desc,
                'endereco'         => $logradouro,
                'numero'           => $numero,
                'bairro'           => $bairro,
                'cidade'           => $cidade,
                'uf'               => $uf,
                'cep'              => $cep ?: null,
                'ibge'             => $ibge,
                'latitude'         => $geo['lat'] ?? null,
                'longitude'        => $geo['lon'] ?? null,
                'inscricao_estadual'   => null,
                'media_compra_mensal'  => null,
                'source_cnpj'      => $cad ? 'brasilapi' : 'cnpj_public',
                'source_endereco'  => $cad ? 'brasilapi|viacep' : 'cnpj_public|viacep',
                'source_geocode'   => $geo ? 'nominatim' : null,
                'enriched_at'      => now(),
            ]);
        }
    }
}
