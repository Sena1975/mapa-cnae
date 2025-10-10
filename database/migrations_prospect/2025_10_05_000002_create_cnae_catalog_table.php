<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void
{
Schema::connection('sqlite_prospect')->create('cnae_catalog', function (Blueprint $table) {
$table->string('codigo', 7)->primary();
$table->string('descricao');
$table->timestamps();
});
}


public function down(): void
{
Schema::connection('sqlite_prospect')->dropIfExists('cnae_catalog');
}
};