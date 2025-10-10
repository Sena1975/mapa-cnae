---
title: Configuração
---

### Variáveis de ambiente (.env)
Exemplo usando **SQLite**:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/caminho/absoluto/para/database/places.sqlite
```
> No Windows, use caminho absoluto e escape de barras quando necessário.

### Estruturas principais
- `geocode_cache` — guarda `input_address`, `lat`, `lng`, `updated_at`
- `places_cache` — guarda `name`, `address`, `lat`, `lng`, `praca`, `updated_at`

### Inspect pelo Artisan (opcional)
```bash
php artisan tinker
```
