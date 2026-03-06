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
        Schema::create('estacoes_meteorologicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->string('nome');
            $table->string('fonte_api');

            $table->decimal('latitude',10,7);
            $table->decimal('longitude',10,7);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estacoes_meteorologicas');
    }
};
