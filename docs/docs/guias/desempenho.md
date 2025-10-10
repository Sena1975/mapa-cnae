---
title: Desempenho e Tuning
---

## Índices
Crie índices conforme consultas mais usadas (exemplos):
```sql
CREATE INDEX IF NOT EXISTS idx_geocode_cache_input ON geocode_cache(input_address);
CREATE INDEX IF NOT EXISTS idx_places_cache_praca ON places_cache(praca);
CREATE INDEX IF NOT EXISTS idx_places_cache_updated ON places_cache(updated_at);
```

## SQLite: WAL e concorrência
```sql
PRAGMA journal_mode = WAL;   -- reduz locks
PRAGMA synchronous = NORMAL; -- equilíbrio entre segurança e velocidade
PRAGMA busy_timeout = 10000; -- espera por locks
```

## Batch e limites
- Ajuste `--limit` para caber na janela de execução.
- Aumente `--sleep` se notar throttling da API externa.

## Observabilidade
- Logue duração total e por lote.
- Registre contagem de sucesso/falhas e motivo.
