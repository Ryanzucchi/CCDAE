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
        Schema::create($this->table('audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('user');
            $table->string('event')->index();
            $table->nullableMorphs('subject');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('audit_logs'));
    }
};
