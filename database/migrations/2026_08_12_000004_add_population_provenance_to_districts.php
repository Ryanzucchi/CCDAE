<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distritos', function (Blueprint $table): void {
            $table->string('populacao_fonte')->nullable()->after('populacao');
            $table->timestamp('populacao_atualizada_em')->nullable()->after('populacao_fonte');
        });
    }
    public function down(): void
    {
        Schema::table('distritos', fn (Blueprint $table) => $table->dropColumn(['populacao_fonte', 'populacao_atualizada_em']));
    }
};
