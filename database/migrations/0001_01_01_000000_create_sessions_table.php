<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codepad has no accounts, so the starter's `users` and
     * `password_reset_tokens` tables are gone. `sessions` stays: the native
     * runtime reaches its screens over real HTTP routes through the `web`
     * middleware group, and `SESSION_DRIVER` is `database`.
     */
    public function up(): void
    {
        /*
         * Guarded because this file was renamed out of the starter's
         * `create_users_table`. Laravel identifies a migration by its
         * FILENAME, so on an install that already ran the old one the new
         * name looks like a new migration — and an unguarded
         * `Schema::create('sessions')` would fail with "table already
         * exists". Migrations run on app start on device, so that failure
         * means the app does not boot.
         */
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};