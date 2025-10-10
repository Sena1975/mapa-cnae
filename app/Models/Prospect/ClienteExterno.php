<?php
namespace App\Models\Prospect;


use Illuminate\Database\Eloquent\Model;


class ClienteExterno extends Model
{
protected $connection = 'sqlite_prospect';
protected $table = 'cliente_externo';


protected $fillable = [
'cnpj','razao_social','nome_fantasia','codigo_cnae','descricao_cnae',
'endereco','numero','bairro','cidade','uf','cep','ibge',
'latitude','longitude','inscricao_estadual','media_compra_mensal',
'source_cnpj','source_endereco','source_geocode','enriched_at'
];


protected $casts = [
'enriched_at' => 'datetime',
'latitude' => 'float',
'longitude' => 'float',
];
}