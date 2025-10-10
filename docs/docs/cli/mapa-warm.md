---
title: Comando `mapa:warm`
---

O comando `mapa:warm` pré‑carrega ("aquece") o cache de geocodificações/lugares para acelerar consultas futuras.

## Uso
```bash
php artisan mapa:warm [--limit=NUM] [--retry=NUM] [--sleep=MS]
```

## Parâmetros
- `--limit` (default recomendado: 30000)
  - Quantidade máxima de itens processados na execução.
- `--retry` (default recomendado: 2)
  - Tentativas em caso de falhas temporárias (ex.: rate limit da API de geocoding).
- `--sleep` (em milissegundos, ex.: 150)
  - Pausa entre requisições para evitar bloqueios e respeitar limites.

## Exemplo (recomendado)
```bash
php artisan mapa:warm --limit=30000 --retry=2 --sleep=150
```

## Boas práticas
- Rode em horários de menor uso.
- Combine com `journal_mode=WAL` no SQLite (menos lock em leitura).
- Aumente `busy_timeout` para evitar `database is locked`.
- Monitore logs e métricas (tempo médio por item, taxa de falhas).

## Agendamento
**Linux (cron):**
```cron
# Aquece diariamente às 02:00
0 2 * * * cd /caminho/do/projeto && php artisan mapa:warm --limit=30000 --retry=2 --sleep=150 >> storage/logs/mapa-warm.log 2>&1
```

**Windows (Task Scheduler):**
- Ação: `Program/script` = `php`
- Argumentos: `artisan mapa:warm --limit=30000 --retry=2 --sleep=150`
- Iniciar em: `C:\xampp\htdocs\mapa-cnae`
