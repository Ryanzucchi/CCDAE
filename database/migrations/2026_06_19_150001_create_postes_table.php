<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('postes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->string('codigo_patrimonio')->unique()->nullable();
            
            // Dados Geográficos
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            // Dados Físicos (Para Módulo de Desgaste)
            $table->enum('material', ['concreto', 'madeira', 'ferro', 'fibra'])->default('concreto');
            $table->decimal('altura_metros', 5, 2)->nullable();
            $table->integer('resistencia_kg')->nullable();
            $table->boolean('possui_iluminacao')->default(false);
            
            // Ciclo de Vida
            $table->date('data_instalacao')->nullable();
            $table->date('ultima_manutencao')->nullable();
            $table->enum('estado_conservacao', ['novo', 'bom', 'regular', 'ruim', 'critico'])->default('bom');
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('postes');
    }
};
