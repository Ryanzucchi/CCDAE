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
        Schema::create($this->table('map_preferences'), function (Blueprint $table) {
            $table->id();
            $table->morphs('user');
            $table->string('map_key');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['user_type', 'user_id', 'map_key', 'key'], 'gv_map_pref_user_map_key_unique');
        });

        Schema::create($this->table('layer_preferences'), function (Blueprint $table) {
            $table->id();
            $table->morphs('user');
            $table->string('map_key');
            $table->string('layer_key');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['user_type', 'user_id', 'map_key', 'layer_key', 'key'], 'gv_layer_pref_user_map_layer_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('layer_preferences'));
        Schema::dropIfExists($this->table('map_preferences'));
    }
};
