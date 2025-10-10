---
title: Base de Dados
---

### Consultas rápidas
```sql
-- Tabelas
SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;

-- Geocodes (contagem)
SELECT COUNT(*) FROM geocode_cache;
SELECT COUNT(*) FROM geocode_cache WHERE lat IS NOT NULL AND lng IS NOT NULL;

-- Últimos geocodes
SELECT input_address, lat, lng, updated_at
FROM geocode_cache
ORDER BY id DESC
LIMIT 10;

-- Praças mais frequentes
SELECT praca, COUNT(*)
FROM places_cache
GROUP BY praca
ORDER BY COUNT(*) DESC
LIMIT 20;

-- Últimos lugares
SELECT name, address, lat, lng, updated_at
FROM places_cache
ORDER BY id DESC
LIMIT 10;
```

### PRAGMAs úteis (SQLite)
```sql
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA busy_timeout = 10000; -- 10s
```
