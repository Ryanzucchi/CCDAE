<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('via_transitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->string('nome')->nullable(); // Nome da rua ou rodovia
            $table->jsonb('geojson')->nullable(); // Geometria da via (LineString)
            $table->string('nivel_congestionamento')->default('livre'); // livre, moderado, intenso, parado
            $table->integer('velocidade_media')->nullable(); // km/h
            $table->integer('volume_veiculos')->nullable(); // veiculos por hora
            $table->enum('impacto_manutencao', ['baixo', 'medio', 'alto'])->default('baixo');
            $table->timestamp('ultima_atualizacao')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('via_transitos');
    }
};
