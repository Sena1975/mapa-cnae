---
title: Acesso via DBeaver
---

1. **Database → New Connection → SQLite**
2. **Download do driver** (primeira vez)
3. **Database file**: aponte para `C:\\xampp\\htdocs\\mapa-cnae\\database\\places.sqlite`
4. (Opcional) **Init SQL**:
   ```sql
   PRAGMA foreign_keys = ON;
   PRAGMA journal_mode = WAL;
   PRAGMA busy_timeout = 10000;
   ```

## Consultas úteis
Veja a página **Base de Dados** para consultas prontas.

## Exportação
No grid de resultados: **Export Data** → CSV/Excel/JSON.
