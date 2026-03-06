<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regiao_climatica_distrito', function (Blueprint $table) {

            $table->id();

            $table->foreignId('regiao_climatica_id')
                ->constrained('regioes_climaticas')
                ->cascadeOnDelete();

            $table->foreignId('distrito_id')
                ->constrained()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regiao_climatica_distrito');
    }
};
