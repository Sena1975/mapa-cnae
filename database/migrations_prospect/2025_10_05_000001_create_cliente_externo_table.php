<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void
{
Schema::connection('sqlite_prospect')->create('cliente_externo', function (Blueprint $table) {
$table->id();
$table->string('cnpj', 14)->nullable()->unique();
$table->string('place_id')->nullable()->unique();
$table->string('razao_social')->nullable();
$table->string('nome_fantasia')->nullable();
$table->string('codigo_cnae', 7)->nullable();
$table->string('descricao_cnae')->nullable();
$table->string('endereco')->nullable();
$table->string('numero', 20)->nullable();
$table->string('bairro')->nullable();
$table->string('cidade')->nullable();
$table->string('uf', 2)->nullable();
$table->string('cep', 8)->nullable();
$table->string('ibge', 7)->nullable();
$table->decimal('latitude', 10, 7)->nullable();
$table->decimal('longitude', 10, 7)->nullable();
$table->string('inscricao_estadual')->nullable(); // ficará NULL
$table->decimal('media_compra_mensal', 12, 2)->nullable(); // ficará NULL
$table->string('source_cnpj')->nullable();
$table->string('source_endereco')->nullable();
$table->string('source_geocode')->nullable();
$table->timestamp('enriched_at')->nullable();
$table->timestamps();


$table->index(['uf', 'cidade']);
$table->index(['latitude', 'longitude']);
});
}


public function down(): void
{
Schema::connection('sqlite_prospect')->dropIfExists('cliente_externo');
}
};