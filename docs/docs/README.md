# mapa-cnae

Aplicação Laravel para exibir **clientes internos** (Oracle) e **externos** (Google Places) em **Google Maps** com filtros dinâmicos, exportação Excel e geocoding com **cache** (SQLite).

## Sumário
- Arquitetura
- Requisitos
- Instalação & Setup
- Configuração do `.env`
- Migrations
- Execução
- Funcionalidades
- API (endpoints)
- Exportação Excel
- Geocoding & Places (cache)
- Pré-aquecimento (warm)
- Agendamentos (Scheduler)
- Performance
- Troubleshooting
- Segurança
- Changelog

---

## Arquitetura
- **Frontend:** Blade + Tailwind + Google Maps JS + MarkerClusterer
- **Backend:** Laravel (PHP 8.2), Oracle (yajra/oci8), Excel (maatwebsite/excel)
- **Cache internos (geocoding):** SQLite `geocode_cache`
- **Cache externos (places):** SQLite `places_cache`
- **Chaves Google:** Maps (JS), Geocoding (server-side), Places (server-side)

## Requisitos
- PHP 8.2+
- Composer
- Laravel 12.x
- OCI8 + `yajra/laravel-oci8`
- SQLite (PDO_SQLITE)
- Chaves Google (Maps, Geocoding e, opcional, Places)

## Instalação & Setup
```bash
composer install
composer require guzzlehttp/guzzle
composer require maatwebsite/excel:^3.1 --with-all-dependencies
composer require yajra/laravel-oci8:"^12.0" --with-all-dependencies
```

## Configuração do `.env`
```ini
# ORACLE
ORACLE_HOST=192.168.254.200
ORACLE_PORT=1521
ORACLE_SERVICE_NAME=DBPROD
ORACLE_USERNAME=CONSULTAPOWERBI
ORACLE_PASSWORD=S0STQUERYPB
ORACLE_SCHEMA=OWNER_DO_OBJETO
ORACLE_TABLE=VIEW_APP_CLIENTE_MAPA

# SQLITE
SQLITE_DB=database/places.sqlite

# GOOGLE
GOOGLE_MAPS_KEY=SUACHAVE_MAPS_JS
GOOGLE_GEOCODE_KEY=SUACHAVE_BACKEND
GOOGLE_PLACES_KEY=SUACHAVE_BACKEND

# PRODUÇÃO
GEOCODE_CACHE_ONLY=true
PLACES_LIVE=false

# TTLs (horas) — 0 = infinito
GEOCODE_CACHE_TTL_HOURS=720
PLACES_CACHE_TTL_HOURS=168

# Raio máximo (externos)
PLACES_MAX_RADIUS_M=15000

# Cache JSON de resposta (minutos)
CLIENTES_CACHE_MIN=5
```

Crie o arquivo do SQLite:
```bash
mkdir -p database
# Windows
type nul > database\places.sqlite
# Linux/macOS
# touch database/places.sqlite
```

## Migrations
```bash
php artisan optimize:clear
php artisan migrate --database=sqlite
```

## Execução
```bash
php artisan serve
# http://127.0.0.1:8000/
```

## Funcionalidades
- Filtros: Cidade, CNAE, Equipe (Supervisor), Vendedor, Ramo
- Marcadores: azul (internos), vermelho (externos)
- Autocomplete para filtros
- Cluster de marcadores
- Exportação Excel (opção incluir externos)

## Endpoints principais
- `GET /api/clientes?cidade=&cnae=&equipe=&vendedor=&ramo=&limit=&debug=`
- `GET /api/externos?cidade=&cnae=&north=&south=&east=&west=&limit=`
- `GET /api/filtros/cidades?q=` (idem para cnaes, equipes, vendedores, ramos)
- `GET /export/excel?...&include_externals=true|false`

## Geocoding & Places (cache)
- Internos (Oracle) geram endereço a partir de: ENDERECO, NUMERO, BAIRRO, CIDADE, UF, CEP.
- Geocoding grava em `geocode_cache` (SQLite) — API usa somente o cache quando `GEOCODE_CACHE_ONLY=true`.
- Externos: `places_cache` armazena respostas de Text Search (Places). Em produção, deixe `PLACES_LIVE=false` e pré-popule.

## Pré-aquecimento (warm)
```bash
php artisan mapa:warm --cidade="Lauro de Freitas" --limit=3000 --retry=2 --sleep=150
php artisan mapa:warm --cnae=5611201 --limit=5000 --retry=2 --sleep=150
php artisan mapa:warm --limit=30000 --retry=2 --sleep=150   # geral (cautela com cota)
```

- `--retry=N` tenta variações do endereço (com/sem CEP, número, bairro)
- `--sleep=ms` pausa entre chamadas (evita OVER_QUERY_LIMIT)
- `--dry-run` simula sem gravar

### Métricas
```bash
php artisan geocode:count
php artisan geocode:stats
php artisan geocode:city-stats --cidade="Salvador" --limit=8000
php artisan places:stats --top=15
```

## Scheduler
`app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('mapa:warm --cidade="Lauro de Freitas" --limit=4000 --retry=2 --sleep=150')->dailyAt('02:00');
    $schedule->command('mapa:warm --cidade="Salvador"        --limit=8000 --retry=2 --sleep=150')->dailyAt('03:00');
}
```
No sistema operacional, agende `php artisan schedule:run` a cada minuto.

## Performance
- **Produção:** `GEOCODE_CACHE_ONLY=true` e `PLACES_LIVE=false`
- Índices no Oracle (function-based):
```sql
CREATE INDEX IX_CLIENTES_CIDADE      ON VIEW_APP_CLIENTE_MAPA (UPPER(CIDADE));
CREATE INDEX IX_CLIENTES_SUPERVISOR  ON VIEW_APP_CLIENTE_MAPA (UPPER(SUPERVISOR));
CREATE INDEX IX_CLIENTES_VENDEDOR    ON VIEW_APP_CLIENTE_MAPA (UPPER(VENDEDOR));
CREATE INDEX IX_CLIENTES_RAMO        ON VIEW_APP_CLIENTE_MAPA (UPPER(RAMO_ATIVIDADE));
CREATE INDEX IX_CLIENTES_CNAE        ON VIEW_APP_CLIENTE_MAPA (CNAE);
CREATE INDEX IX_CLIENTES_ULTCOMPRA   ON VIEW_APP_CLIENTE_MAPA (DATA_ULTIMA_COMPRA DESC);
```

## Troubleshooting
- **Sem internos no mapa:** rode o warm para a cidade do filtro e garanta `GEOCODE_CACHE_ONLY=true`.
- **InfoWindow mostra `${...}`:** use a versão de `makeMarker()` com concatenação de strings.
- **Tinker com erro `psysh`:** reinstale vendor (`composer clear-cache`, remover `vendor/psy/psysh`, `composer require laravel/tinker:^2.9`).

## Segurança
- Restrinja a chave JS por domínio (referrer) e a de backend por IP.
- Não commitar `.env` nem credenciais.

## Changelog
- v1.1: Cidade no filtro, geocoding cache-only, comandos artesanais (warm e métricas), `makeMarker` robusto.
- v1.0: Versão inicial (mapa, filtros, exportação, Oracle + Places, caches).
