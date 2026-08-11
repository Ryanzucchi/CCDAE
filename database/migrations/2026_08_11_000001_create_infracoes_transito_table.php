<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infracoes_transito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('via_transito_id')->nullable()->constrained('via_transitos')->nullOnDelete();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('codigo', 40)->nullable();
            $table->string('tipo', 80);
            $table->string('placa', 10)->nullable();
            $table->string('descricao', 500)->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            $table->unsignedSmallInteger('pontos')->default(0);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('ocorrido_em');
            $table->string('situacao', 30)->default('registrada');
            $table->timestamps();

            $table->index(['ocorrido_em', 'tipo']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infracoes_transito');
    }
};
