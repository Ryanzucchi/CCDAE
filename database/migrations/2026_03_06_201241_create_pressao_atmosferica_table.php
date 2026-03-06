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
        Schema::create('pressao_atmosferica', function (Blueprint $table) {

            $table->id();

            $table->foreignId('distrito_id')->constrained()->cascadeOnDelete();

            $table->timestamp('timestamp');

            $table->float('pressao_hpa');

            $table->primary(['distrito_id','timestamp']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pressao_atmosferica');
    }
};
