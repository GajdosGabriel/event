<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spotreba OpenAI — jeden riadok na volanie (App\Services\OpenAI\AiUsageRecorder).
 * Podklad pre prehľad v administrácii: čo koľko tokenov berie a za koľko.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_usages')) {
            return;
        }

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->increments('id');
            // Operácia (metóda ChatGPT): event_detection, copywriter, tags…
            $table->string('feature', 40)->index();
            // Odkiaľ volanie prišlo: meno routy alebo artisan príkaz.
            $table->string('source', 120)->nullable();
            $table->string('model', 60);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->boolean('success')->default(true);
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('canal_id')->nullable()->index();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
