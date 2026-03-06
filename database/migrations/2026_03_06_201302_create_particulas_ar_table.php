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
        Schema::create('particulas_ar', function (Blueprint $table) {

            $table->id();

            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->timestamp('timestamp');

            $table->float('pm25')->nullable();
            $table->float('pm10')->nullable();
            $table->float('poeira')->nullable();
            $table->float('areia')->nullable();
            $table->float('poluentes')->nullable();

            $table->primary(['distrito_id','timestamp']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('particulas_ar');
    }
};
