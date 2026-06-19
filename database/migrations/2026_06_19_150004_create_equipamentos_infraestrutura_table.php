<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('equipamentos_infraestrutura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->foreignId('poste_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->foreignId('central_id')->nullable()->constrained('centrais_distribuicao')->nullOnDelete();
            
            $table->string('nome');
            $table->string('codigo_patrimonio')->unique()->nullable();
            $table->string('tipo')->nullable(); // Transformador, Roteador Core, etc
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            $table->date('data_instalacao')->nullable();
            $table->date('ultima_manutencao')->nullable();
            $table->enum('estado_conservacao', ['novo', 'bom', 'regular', 'ruim', 'critico'])->default('bom');
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('equipamentos_infraestrutura');
    }
};
