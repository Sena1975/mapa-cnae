<?php
namespace App\Models\Prospect;


use Illuminate\Database\Eloquent\Model;


class CnpjRefEstabelecimento extends Model
{
protected $connection = 'sqlite_prospect';
protected $table = 'cnpj_ref_estabelecimento';
protected $fillable = [
'cnpj','razao_social','nome_fantasia','cnae_principal','logradouro','numero','bairro',
'municipio','uf','cep','situacao_cadastral'
];
}