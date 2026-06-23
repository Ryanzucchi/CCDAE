<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SELECT create_hypertable('registro_transitos','timestamp', if_not_exists => TRUE)");
    }

    public function down(): void
    {
        // TimescaleDB hypertables are dropped when the table is dropped
    }
};
