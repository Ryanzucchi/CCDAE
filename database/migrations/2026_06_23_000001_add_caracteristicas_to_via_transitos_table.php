<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('via_transitos', function (Blueprint $table) {
            $table->string('tipo')->nullable(); // avenida, rua, rodovia
            $table->integer('numero_faixas')->nullable();
            $table->string('sentido')->nullable(); // unica, dupla
            $table->integer('limite_velocidade')->nullable();
            $table->float('inclinacao_pista')->nullable();
            $table->float('altura_maxima_permitida')->nullable();
            $table->float('largura_maxima_permitida')->nullable();
            $table->float('peso_maximo_permitido')->nullable();
            $table->boolean('pedagio')->default(false);
            $table->string('estado_pavimento')->nullable();
            $table->boolean('ciclovia')->default(false);
            $table->boolean('faixas_exclusivas')->default(false); // onibus, caminhao, etc
            $table->jsonb('infraestrutura_json')->nullable(); // semaforos, lombadas, cruzamentos
        });
    }

    public function down(): void
    {
        Schema::table('via_transitos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'numero_faixas',
                'sentido',
                'limite_velocidade',
                'inclinacao_pista',
                'altura_maxima_permitida',
                'largura_maxima_permitida',
                'peso_maximo_permitido',
                'pedagio',
                'estado_pavimento',
                'ciclovia',
                'faixas_exclusivas',
                'infraestrutura_json'
            ]);
        });
    }
};
