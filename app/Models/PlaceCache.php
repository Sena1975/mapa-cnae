<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceCache extends Model
{
    protected $connection = 'sqlite';
    protected $table = 'places_cache';
    protected $fillable = [
        'cnae','keyword','query_hash','place_id','name','lat','lng','address','types',
        'praca','north','south','east','west','radius_m','source'
    ];
}
