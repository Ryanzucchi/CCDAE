<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('temperatura_registrada', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('vento_registrado', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('chuva_registrada', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('pressao_atmosferica', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('radiacao_solar', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('indice_uv', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

        Schema::table('particulas_ar', function (Blueprint $table) {
            $table->index(['distrito_id','timestamp']);
        });

    }

    public function down(): void
    {

    }
};
