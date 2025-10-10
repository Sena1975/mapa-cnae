---
title: FAQ
---

### `sqlite3: unable to open database file`
Verifique o caminho absoluto em `DB_DATABASE`.

### `no such table: ...`
Atualize migrações/seed. Confirme o arquivo correto do SQLite.

### `database is locked`
Ative WAL, aumente `busy_timeout` e evite múltiplas conexões de escrita simultâneas.

### O `mapa:warm` ficou lento
Aumente `--sleep`, reduza `--limit`, e crie índices conforme o padrão de leitura.
