<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::connection('sqlite')->hasTable('geocode_cache')) {
            Schema::connection('sqlite')->create('geocode_cache', function (Blueprint $table) {
                $table->id();
                $table->string('addr_hash', 64)->unique();
                $table->string('input_address', 500);
                $table->string('formatted_address', 500)->nullable();
                $table->decimal('lat', 10, 6)->nullable();
                $table->decimal('lng', 10, 6)->nullable();
                $table->string('provider', 50)->default('google');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlite')->dropIfExists('geocode_cache');
    }
};
