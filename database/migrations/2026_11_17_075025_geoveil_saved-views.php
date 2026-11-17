<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function table(string $name): string
    {
        return (string) config('geoveil.table_prefix', 'geoveil_').$name;
    }

    public function up(): void
    {
        Schema::create($this->table('saved_views'), function (Blueprint $table) {
            $table->id();
            $table->morphs('user');
            $table->string('map_key')->index();
            $table->string('name');
            $table->json('definition');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('saved_views'));
    }
};
