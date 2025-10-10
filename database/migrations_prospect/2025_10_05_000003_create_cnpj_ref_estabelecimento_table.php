<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void
{
Schema::connection('sqlite_prospect')->create('cnpj_ref_estabelecimento', function (Blueprint $table) {
$table->id();
$table->string('cnpj', 14)->index();
$table->string('razao_social')->nullable();
$table->string('nome_fantasia')->nullable();
$table->string('cnae_principal', 7)->index();
$table->string('logradouro')->nullable();
$table->string('numero', 20)->nullable();
$table->string('bairro')->nullable();
$table->string('municipio')->nullable()->index();
$table->string('uf', 2)->nullable()->index();
$table->string('cep', 8)->nullable()->index();
$table->string('situacao_cadastral', 20)->nullable()->index(); // "ATIVA" etc.
$table->timestamps();
$table->unique(['cnpj']);
});
}


public function down(): void
{
Schema::connection('sqlite_prospect')->dropIfExists('cnpj_ref_estabelecimento');
}
};