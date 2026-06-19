<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cabeamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->string('nome')->nullable();
            $table->string('codigo_patrimonio')->unique()->nullable();
            
            $table->enum('tipo_cabo', ['fibra_optica', 'cobre_telefonia', 'eletrico_alta_tensao', 'eletrico_baixa_tensao'])->nullable();
            $table->string('capacidade')->nullable();
            $table->string('revestimento')->nullable();
            $table->boolean('subterraneo')->default(false);
            $table->decimal('extensao_metros', 10, 2)->nullable();
            
            $table->json('geojson')->nullable(); // LineString representation
            
            // Conexões
            $table->foreignId('poste_origem_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->foreignId('poste_destino_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->foreignId('central_origem_id')->nullable()->constrained('centrais_distribuicao')->nullOnDelete();
            $table->foreignId('central_destino_id')->nullable()->constrained('centrais_distribuicao')->nullOnDelete();
            
            $table->date('data_instalacao')->nullable();
            $table->date('ultima_manutencao')->nullable();
            $table->enum('estado_conservacao', ['novo', 'bom', 'regular', 'ruim', 'critico'])->default('bom');
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('cabeamentos');
    }
};
