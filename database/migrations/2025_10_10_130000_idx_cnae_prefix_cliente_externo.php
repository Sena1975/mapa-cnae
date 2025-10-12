<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::connection('sqlite_prospect')->statement(
            "CREATE INDEX IF NOT EXISTS idx_cli_ext_cnae_prefix ON cliente_externo(substr(codigo_cnae,1,7))"
        );
    }
    public function down(): void
    {
        DB::connection('sqlite_prospect')->statement(
            "DROP INDEX IF EXISTS idx_cli_ext_cnae_prefix"
        );
    }
};
