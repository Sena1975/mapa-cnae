<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $connection = 'sqlite';
    protected $table = 'geocode_cache';
    protected $fillable = [
        'addr_hash','input_address','formatted_address','lat','lng','provider'
    ];
}
