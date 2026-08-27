<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_tiles', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30)->default('tomtom');
            $table->unsignedTinyInteger('zoom');
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->boolean('active')->default(true);
            $table->timestamp('last_collected_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'zoom', 'x', 'y']);
            $table->index(['active', 'last_collected_at']);
        });

        Schema::create('traffic_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('via_transito_id')->nullable()->constrained('via_transitos')->nullOnDelete();
            $table->string('provider', 30)->default('tomtom');
            $table->string('segment_identifier', 100);
            $table->jsonb('geometry');
            $table->string('road_type', 60)->nullable();
            $table->string('road_coverage', 30)->nullable();
            $table->string('direcao_fluxo', 20)->nullable();
            $table->boolean('left_hand_traffic')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'segment_identifier']);
            $table->index('via_transito_id');
        });

        Schema::create('traffic_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('traffic_segment_id')->constrained('traffic_segments')->cascadeOnDelete();
            $table->timestamp('collected_at');
            $table->float('current_speed')->nullable();
            $table->float('free_flow_speed')->nullable();
            $table->decimal('congestion_ratio', 6, 5)->nullable();
            $table->decimal('quality', 5, 4)->nullable();
            $table->boolean('road_closed')->default(false);
            $table->string('direcao_fluxo', 20)->nullable();
            $table->string('provider', 30)->default('tomtom');
            $table->unsignedTinyInteger('tile_zoom');
            $table->unsignedInteger('tile_x');
            $table->unsignedInteger('tile_y');
            $table->unique(['traffic_segment_id', 'collected_at']);
            $table->index('collected_at');
        });

        Schema::create('traffic_api_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30);
            $table->date('usage_date');
            $table->string('endpoint', 60);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('successful_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->timestamps();
            $table->unique(['provider', 'usage_date', 'endpoint']);
        });

        Schema::table('registro_transitos', function (Blueprint $table): void {
            $table->float('velocidade_fluxo_livre')->nullable()->after('velocidade_media');
            $table->decimal('razao_congestionamento', 6, 5)->nullable()->after('velocidade_fluxo_livre');
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['traffic_tiles', 'traffic_segments', 'traffic_observations', 'traffic_api_usage'] as $table) {
                DB::statement("ALTER TABLE public.{$table} SET SCHEMA mobility");
            }
            DB::statement('ALTER TABLE mobility.traffic_segments ADD COLUMN geom geometry(MultiLineString, 4326)');
            DB::statement(<<<'SQL'
                UPDATE mobility.traffic_segments
                SET geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(geometry::text), 4326))
                WHERE geometry IS NOT NULL
                SQL);
            DB::statement('CREATE INDEX traffic_segments_geom_gist ON mobility.traffic_segments USING GIST (geom)');
        }
    }

    public function down(): void
    {
        Schema::table('registro_transitos', function (Blueprint $table): void {
            $table->dropColumn(['velocidade_fluxo_livre', 'razao_congestionamento']);
        });

        foreach (['traffic_api_usage', 'traffic_observations', 'traffic_segments', 'traffic_tiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
