<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

        DB::statement("SELECT create_hypertable('temperatura_registrada','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('vento_registrado','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('chuva_registrada','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('pressao_atmosferica','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('radiacao_solar','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('indice_uv','timestamp', if_not_exists => TRUE)");

        DB::statement("SELECT create_hypertable('particulas_ar','timestamp', if_not_exists => TRUE)");

    }

};

