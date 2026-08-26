<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('via_transitos', function (Blueprint $table): void {
            $table->string('osm_way_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('via_transitos', function (Blueprint $table): void {
            $table->dropUnique(['osm_way_id']);
            $table->dropColumn('osm_way_id');
        });
    }
};
