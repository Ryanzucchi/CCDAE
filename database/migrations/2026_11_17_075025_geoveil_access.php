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
        Schema::create($this->table('roles'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create($this->table('permissions'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('area')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($this->table('role_permissions'), function (Blueprint $table) {
            $table->foreignId('role_id')->constrained($this->table('roles'))->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained($this->table('permissions'))->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create($this->table('user_roles'), function (Blueprint $table) {
            $table->foreignId('role_id')->constrained($this->table('roles'))->cascadeOnDelete();
            $table->morphs('user');
            $table->primary(['role_id', 'user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('user_roles'));
        Schema::dropIfExists($this->table('role_permissions'));
        Schema::dropIfExists($this->table('permissions'));
        Schema::dropIfExists($this->table('roles'));
    }
};
