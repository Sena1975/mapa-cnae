<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlite_prospect')->table('cliente_externo', function (Blueprint $table) {
            // Torna CNPJ opcional (SQLite ignora change(), então recriamos a coluna via workaround quando necessário)
            // Em SQLite, alterar nullability costuma exigir tabela temporária; para simplificar:
            // Se sua base ainda está vazia, você pode dropar e recriar. Caso contrário, mantenha o comentário abaixo.

            // Tenta adicionar place_id para deduplicação do Google
            if (!Schema::connection('sqlite_prospect')->hasColumn('cliente_externo', 'place_id')) {
                $table->string('place_id')->nullable()->unique()->after('id');
            }

            // Se você puder, rode manualmente:
            // ALTER TABLE cliente_externo RENAME TO cliente_externo_old;
            // ... recria a tabela com cnpj nullable ...
            // ... insere de volta os dados ...
            // Como alternativa prática, se a tabela estiver vazia, faça um rollback e ajuste a migração original para ->nullable()
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_prospect')->table('cliente_externo', function (Blueprint $table) {
            if (Schema::connection('sqlite_prospect')->hasColumn('cliente_externo', 'place_id')) {
                $table->dropColumn('place_id');
            }
            // não voltamos cnpj para NOT NULL para evitar perdas
        });
    }
};
