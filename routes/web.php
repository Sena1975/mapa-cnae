<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('mapa.index', [
        'googleKey' => env('GOOGLE_MAPS_KEY'),
    ]);
});
