<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('antenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->string('codigo_patrimonio')->unique()->nullable();
            
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            $table->string('tipo_sinal')->nullable(); // 5G, 4G, Radio
            $table->decimal('frequencia_mhz', 8, 2)->nullable();
            $table->decimal('alcance_metros', 8, 2)->nullable();
            $table->decimal('potencia_dbm', 8, 2)->nullable();
            $table->string('proprietario')->nullable(); // Telecom owner
            
            $table->date('data_instalacao')->nullable();
            $table->date('ultima_manutencao')->nullable();
            $table->enum('estado_conservacao', ['novo', 'bom', 'regular', 'ruim', 'critico'])->default('bom');
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('antenas');
    }
};
