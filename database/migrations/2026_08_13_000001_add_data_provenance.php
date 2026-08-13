<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registro_transitos', function (Blueprint $table): void {
            $table->string('origem_dado', 30)->default('simulado');
            $table->string('fonte_dado', 120)->nullable();
            $table->decimal('confianca_dado', 5, 4)->nullable();
        });
        foreach (['temperatura_registrada', 'vento_registrado', 'chuva_registrada', 'pressao_atmosferica', 'radiacao_solar', 'indice_uv', 'particulas_ar'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('origem_dado', 30)->default('simulado');
                $blueprint->string('fonte_dado', 120)->nullable();
            });
        }
        Schema::table('antenas', function (Blueprint $table): void {
            $table->string('fonte_dado', 50)->nullable();
            $table->string('external_id', 100)->nullable();
            $table->jsonb('tags_origem')->nullable();
            $table->timestamp('sincronizado_em')->nullable();
            $table->unique(['fonte_dado', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('registro_transitos', fn (Blueprint $table) => $table->dropColumn(['origem_dado', 'fonte_dado', 'confianca_dado']));
        foreach (['temperatura_registrada', 'vento_registrado', 'chuva_registrada', 'pressao_atmosferica', 'radiacao_solar', 'indice_uv', 'particulas_ar'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(['origem_dado', 'fonte_dado']));
        }
        Schema::table('antenas', function (Blueprint $table): void {
            $table->dropUnique(['fonte_dado', 'external_id']);
            $table->dropColumn(['fonte_dado', 'external_id', 'tags_origem', 'sincronizado_em']);
        });
    }
};
