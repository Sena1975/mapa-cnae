<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::connection('sqlite')->hasTable('places_cache')) {
            Schema::connection('sqlite')->create('places_cache', function (Blueprint $table) {
                $table->id();
                $table->string('cnae', 20)->nullable()->index();
                $table->string('keyword', 255)->nullable()->index();
                $table->string('query_hash', 64)->index();
                $table->string('place_id', 128)->index();
                $table->string('name', 255)->nullable();
                $table->decimal('lat', 10, 6)->nullable();
                $table->decimal('lng', 10, 6)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('types', 500)->nullable();
                $table->string('praca', 120)->nullable()->index();
                $table->decimal('north', 10, 6)->nullable();
                $table->decimal('south', 10, 6)->nullable();
                $table->decimal('east', 10, 6)->nullable();
                $table->decimal('west', 10, 6)->nullable();
                $table->integer('radius_m')->nullable();
                $table->string('source', 30)->default('places');
                $table->timestamps();
                $table->unique(['query_hash','place_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlite')->dropIfExists('places_cache');
    }
};
