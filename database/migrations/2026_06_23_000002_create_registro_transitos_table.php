<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_transitos', function (Blueprint $table) {
            $table->foreignId('via_transito_id')->constrained('via_transitos')->cascadeOnDelete();
            $table->timestamp('timestamp');

            // Fluxo
            $table->integer('veiculos_total')->nullable();
            $table->float('velocidade_media')->nullable();
            $table->float('velocidade_min')->nullable();
            $table->float('velocidade_max')->nullable();
            $table->float('tempo_medio_travessia')->nullable();
            
            // Veículos
            $table->float('altura_media_veiculos')->nullable();
            $table->float('altura_maxima_veiculos')->nullable();
            $table->float('peso_medio_veiculos')->nullable();
            $table->float('peso_maximo_veiculos')->nullable();
            $table->float('percentual_veiculos_pesados')->nullable();
            $table->float('taxa_veiculos_eletricos')->nullable();

            // Eventos
            $table->integer('acidentes_ativos')->nullable();
            $table->integer('obras_ativas')->nullable();
            $table->boolean('alagamento_ativo')->default(false);

            // Clima/Ambiente (Snapshot)
            $table->float('chuva_mm')->nullable();
            $table->float('visibilidade')->nullable();
            $table->float('temperatura')->nullable();
            $table->float('nivel_ruido')->nullable();
            $table->float('emissao_co2')->nullable();

            // Indicadores
            $table->integer('indice_congestionamento')->nullable();
            $table->string('nivel_servico')->nullable();

            // Dados avançados
            $table->jsonb('dados_avancados')->nullable();

            $table->primary(['via_transito_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_transitos');
    }
};
