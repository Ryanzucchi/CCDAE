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
        Schema::create('eventos_climaticos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->string('tipo');

            $table->timestamp('inicio');
            $table->timestamp('fim')->nullable();

            $table->text('descricao')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_climaticos');
    }
};
