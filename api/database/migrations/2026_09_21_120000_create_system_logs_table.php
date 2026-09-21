<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denník udalostí — čo komu odišlo, čo zlyhalo, kto sa prihlásil, ako dopadol
 * import (App\Listeners\SystemLogSubscriber, App\Services\SystemLog\Recorder).
 * Krátka pamäť: info záznamy sa po mesiaci mažú (App\Models\SystemLog::prunable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_logs')) {
            return;
        }

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 8)->default('info');
            // Oblasť: mail, auth, queue, scheduler, import, openai…
            $table->string('channel', 32);
            // Kanál + udalosť: mail.sent, mail.failed, auth.login…
            $table->string('event', 64);
            $table->string('status', 16)->nullable();
            $table->string('message', 255)->default('');
            $table->string('recipient', 191)->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['channel', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
