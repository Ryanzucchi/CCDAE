<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
        ALTER TABLE temperatura_registrada
        SET (timescaledb.compress, timescaledb.compress_segmentby = 'distrito_id')
        ");

        DB::statement("
        SELECT add_compression_policy('temperatura_registrada', INTERVAL '7 days')
        ");

    }
};
