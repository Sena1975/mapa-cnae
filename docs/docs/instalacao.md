---
title: Instalação
---

## Pré‑requisitos
- **PHP 8.1+** e **Composer**
- **Laravel** (projeto já clonado)
- **SQLite** (ou outro driver suportado pelo Laravel)
- **Node.js 18+** (apenas se for construir o site Docusaurus local)

## Passos
1. Clone o repositório e instale dependências:
   ```bash
   git clone <repo>
   cd <repo>
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
2. Configure o banco (ver página **Configuração**).
3. Rode as migrações/seeds se aplicável:
   ```bash
   php artisan migrate --seed
   ```
4. Suba o servidor de desenvolvimento:
   ```bash
   php artisan serve
   ```
