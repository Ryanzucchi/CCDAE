<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distritos', function (Blueprint $table): void {
            $table->unsignedBigInteger('populacao')->nullable()->after('cidade');
            $table->decimal('consumo_medio_kwh', 14, 2)->nullable()->after('populacao');
        });

        Schema::create('incidentes_energia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->nullOnDelete();
            $table->foreignId('poste_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->foreignId('cabeamento_id')->nullable()->constrained('cabeamentos')->nullOnDelete();
            $table->foreignId('equipamento_id')->nullable()->constrained('equipamentos_infraestrutura')->nullOnDelete();
            $table->foreignId('central_id')->nullable()->constrained('centrais_distribuicao')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 60);
            $table->string('causa', 120)->nullable();
            $table->text('descricao')->nullable();
            $table->enum('severidade', ['baixa', 'media', 'alta', 'critica'])->default('media');
            $table->enum('situacao', ['registrado', 'em_atendimento', 'resolvido', 'cancelado'])->default('registrado');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('ocorrido_em');
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();
            $table->index(['ocorrido_em', 'tipo']);
            $table->index(['latitude', 'longitude']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS energy');
            DB::statement('ALTER TABLE public.incidentes_energia SET SCHEMA energy');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE IF EXISTS energy.incidentes_energia SET SCHEMA public');
        }
        Schema::dropIfExists('incidentes_energia');
        Schema::table('distritos', fn (Blueprint $table) => $table->dropColumn(['populacao', 'consumo_medio_kwh']));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS energy');
        }
    }
};
