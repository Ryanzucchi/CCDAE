<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperatura_registrada', function (Blueprint $table) {

            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->timestamp('timestamp');

            $table->float('temperatura');

            $table->float('sensacao_termica')->nullable();

            // chave primária composta
            $table->primary(['distrito_id','timestamp']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperatura_registrada');
    }
};
