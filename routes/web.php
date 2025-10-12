<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ExternoController;
use App\Http\Controllers\FiltroController;
use App\Http\Controllers\Prospect\ClienteExternoController;
use App\Http\Controllers\Prospect\ProspectTriggerController;

Route::get('/externals/count', [ClienteExternoController::class, 'countInBounds']);
Route::get('/externals', [ClienteExternoController::class, 'listInBounds']);
Route::get('/clientes', [ClienteController::class, 'list']);
Route::get('/externos', [ExternoController::class, 'list']);
Route::post('/externals/prospect', [ProspectTriggerController::class, 'dispatch']);
Route::get('/filtros/cnaes', [FiltroController::class, 'cnaes']);

// contagem e listagem para o front
Route::get('/externals/count', [ClienteExternoController::class, 'countInBounds']);
Route::get('/externals', [ClienteExternoController::class, 'listInBounds']);

Route::prefix('filtros')->group(function () {
    Route::get('/cidades',    [FiltroController::class, 'cidades']);
    Route::get('/cnaes',      [FiltroController::class, 'cnaes']);
    Route::get('/equipes',    [FiltroController::class, 'equipes']);
    Route::get('/vendedores', [FiltroController::class, 'vendedores']);
    Route::get('/ramos',      [FiltroController::class, 'ramos']);
});
