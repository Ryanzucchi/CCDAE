<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temperatura_registrada', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->timestamp('timestamp');

            $table->float('temperatura');
            $table->float('sensacao_termica')->nullable();

            $table->unique(['distrito_id','timestamp']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temperatura_registrada');
    }
};
