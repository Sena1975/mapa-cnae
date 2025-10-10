<?php
namespace App\Models\Prospect;


use Illuminate\Database\Eloquent\Model;


class CnaeCatalog extends Model
{
protected $connection = 'sqlite_prospect';
protected $table = 'cnae_catalog';
protected $primaryKey = 'codigo';
public $incrementing = false;
protected $keyType = 'string';
protected $fillable = ['codigo','descricao'];
}