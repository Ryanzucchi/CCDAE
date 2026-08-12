<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidentes_internet', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->foreignId('antena_id')->nullable()->constrained('antenas')->nullOnDelete();
            $table->foreignId('cabeamento_id')->nullable()->constrained('cabeamentos')->nullOnDelete();
            $table->foreignId('equipamento_id')->nullable()->constrained('equipamentos_infraestrutura')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 60);
            $table->string('causa', 120)->nullable();
            $table->text('descricao')->nullable();
            $table->enum('severidade', ['baixa', 'media', 'alta', 'critica'])->default('media');
            $table->enum('situacao', ['registrado', 'em_atendimento', 'resolvido', 'cancelado'])->default('registrado');
            $table->unsignedInteger('usuarios_afetados')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('ocorrido_em');
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();
            $table->index(['ocorrido_em', 'tipo']);
            $table->index(['latitude', 'longitude']);
        });

        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('CREATE SCHEMA IF NOT EXISTS internet');
        DB::statement('ALTER TABLE public.incidentes_internet SET SCHEMA internet');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW tiles.internet_cabeamentos AS
            SELECT id, distrito_id, nome, codigo_patrimonio, tipo_cabo, capacidade, revestimento,
                   subterraneo, extensao_metros, estado_conservacao, poste_origem_id, poste_destino_id, geom
            FROM infrastructure.cabeamentos
            WHERE geom IS NOT NULL AND tipo_cabo IN ('fibra_optica', 'fibra_backbone', 'cobre_telefonia');
            CREATE OR REPLACE VIEW tiles.internet_antenas AS
            SELECT id, distrito_id, codigo_patrimonio, tipo_sinal, frequencia_mhz, alcance_metros,
                   potencia_dbm, proprietario, estado_conservacao, geom
            FROM infrastructure.antenas WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.internet_equipamentos AS
            SELECT id, distrito_id, poste_id, central_id, nome, codigo_patrimonio, tipo, modelo,
                   estado_conservacao, geom FROM infrastructure.equipamentos_infraestrutura
            WHERE geom IS NOT NULL AND LOWER(COALESCE(tipo, '')) NOT IN ('transformador', 'medidor');
            COMMENT ON VIEW tiles.internet_cabeamentos IS '{"description":"Rede local de telecomunicações","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.internet_antenas IS '{"description":"Antenas de telecomunicações","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.internet_equipamentos IS '{"description":"Equipamentos de rede","minzoom":10,"maxzoom":22}';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS tiles.internet_cabeamentos, tiles.internet_antenas, tiles.internet_equipamentos');
            DB::statement('ALTER TABLE IF EXISTS internet.incidentes_internet SET SCHEMA public');
        }
        Schema::dropIfExists('incidentes_internet');
        if (DB::getDriverName() === 'pgsql') DB::statement('DROP SCHEMA IF EXISTS internet');
    }
};
