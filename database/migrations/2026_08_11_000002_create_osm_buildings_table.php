<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osm_buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('osm_id');
            $table->string('osm_type', 16);
            $table->string('building', 100)->nullable();
            $table->string('name')->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('building_levels', 8, 2)->nullable();
            $table->decimal('min_height', 10, 2)->nullable();
            $table->decimal('building_min_level', 8, 2)->nullable();
            $table->decimal('render_height', 10, 2);
            $table->decimal('render_min_height', 10, 2)->default(0);
            $table->string('height_source', 20);
            $table->json('tags')->nullable();
            $table->timestampTz('synced_at');
            $table->timestamps();
            $table->unique(['osm_type', 'osm_id']);
            $table->index('osm_id');
            $table->index(['building', 'synced_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE osm_buildings ADD COLUMN geometry geometry(MultiPolygon, 4326)');
            DB::statement('CREATE INDEX osm_buildings_geometry_gist ON osm_buildings USING GIST (geometry)');
        } else {
            Schema::table('osm_buildings', fn (Blueprint $table) => $table->json('geometry')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('osm_buildings');
    }
};
