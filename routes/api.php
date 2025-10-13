<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ExternoController;
use App\Http\Controllers\FiltroController;
use App\Http\Controllers\Prospect\ClienteExternoController;
use App\Http\Controllers\Prospect\ProspectTriggerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Prefix automático: /api
| Ex.: GET /api/externals, GET /api/externals/search, etc.
*/

// =========================
//  Externos (Prospect)
// =========================

// Leitura para o mapa (já persistidos no SQLite: cliente_externo)
Route::get('/externals',       [ClienteExternoController::class, 'listInBounds']);  // ?south&west&north&east[&cnae][&limit][&offset]
Route::get('/externals/count', [ClienteExternoController::class, 'countInBounds']); // ?south&west&north&east[&cnae]

// Popular / atualizar base de externos via Google Places e gravar em cliente_externo
// Ex.: /api/externals/search?lat=-12.9719&lng=-38.5016&cnae=4321500[&radius=8000]
Route::get('/externals/search', [ClienteExternoController::class, 'buscar']);

// (Opcional) Disparador assíncrono/filas, se você usar
Route::post('/externals/prospect', [ProspectTriggerController::class, 'dispatch']);


// =========================
//  Internos e listas auxiliares
// =========================

// Seus clientes internos (VIEW Oracle) para o grid/mapa
Route::get('/clientes', [ClienteController::class, 'list']);

// Legacy (se você ainda usa esse endpoint em alguma parte do front)
Route::get('/externos', [ExternoController::class, 'list']);

// Filtros para a UI (cidades, cnaes, equipes, vendedores, ramos)
Route::prefix('filtros')->group(function () {
    Route::get('/cidades',    [FiltroController::class, 'cidades']);
    Route::get('/cnaes',      [FiltroController::class, 'cnaes']);
    Route::get('/equipes',    [FiltroController::class, 'equipes']);
    Route::get('/vendedores', [FiltroController::class, 'vendedores']);
    Route::get('/ramos',      [FiltroController::class, 'ramos']);
});


// =========================
//  Debug opcional (só em DEV)
// =========================
if (config('app.debug')) {
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Http;

    // Ping simples
    Route::get('/_debug/ping', fn () => ['ok' => true, 'ts' => now()->toDateTimeString()]);

    // Insere um registro “dummy” em cliente_externo para validar escrita no SQLite
    Route::get('/_debug/externals/insert-dummy', function () {
        DB::connection('sqlite_prospect')
            ->table('cliente_externo')
            ->updateOrInsert(
                ['place_id' => 'dbg_123'],
                [
                    'cnpj' => null,
                    'razao_social'   => 'Dummy Solar LTDA',
                    'nome_fantasia'  => 'Dummy Solar',
                    'codigo_cnae'    => '4321500',
                    'descricao_cnae' => 'Instalação de painéis solares',
                    'endereco'       => 'Rua Teste, 100',
                    'cidade'         => 'Salvador',
                    'uf'             => 'BA',
                    'cep'            => '40000000',
                    'latitude'       => -12.9719,
                    'longitude'      => -38.5016,
                    'source_endereco'=> 'debug',
                    'source_geocode' => 'debug',
                    'enriched_at'    => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );

        $count = DB::connection('sqlite_prospect')->table('cliente_externo')->count();
        return ['ok' => true, 'count' => $count];
    });

    // Chamada direta ao Google Places (sem persistir) para checar chave/retorno
    Route::get('/_debug/places', function (\Illuminate\Http\Request $req) {
        $lat     = $req->query('lat', -12.9719);
        $lng     = $req->query('lng', -38.5016);
        $keyword = $req->query('q',  'energia solar|instalação de painéis solares|solar|fotovoltaica|placas solares|solar installer');
        $radius  = $req->query('r',  5000);

        $resp = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => "$lat,$lng",
            'radius'   => $radius,
            'keyword'  => $keyword,
            'key'      => env('GOOGLE_PLACES_KEY'),
        ]);

        return response($resp->body(), $resp->status())->header('Content-Type', 'application/json');
    });
}
