<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dotazník k registrácii — otázky organizátora (strava, ubytovanie…),
     * ich možnosti (s voliteľnou cenou a kapacitou) a odpovede k objednávke
     * alebo k jednotlivému miestu.
     */
    public function up(): void
    {
        Schema::create('event_questions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->string('scope', 20)->default('attendee');
            $table->string('type', 20);
            $table->string('label', 250);
            $table->string('help', 500)->nullable();
            $table->boolean('is_required')->default(false);
            // null = všetky hlavné typy lístkov (bez workshopov).
            $table->json('ticket_type_ids')->nullable();
            $table->boolean('editable_by_attendee')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('event_question_options', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('question_id');
            $table->string('label', 250);
            $table->unsignedInteger('price_amount')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('event_questions')->cascadeOnDelete();
        });

        Schema::create('ticket_answers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('question_id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('admission_id')->nullable();
            $table->unsignedInteger('option_id')->nullable();
            $table->text('value')->nullable();
            // Cena možnosti v čase odpovede (centy) — neskoršia zmena ceny ju nemení.
            $table->unsignedInteger('price_amount')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('event_questions')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('admission_id')->references('id')->on('ticket_admissions')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('event_question_options')->nullOnDelete();
            $table->index(['ticket_id', 'admission_id', 'question_id']);
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_answers');
        Schema::dropIfExists('event_question_options');
        Schema::dropIfExists('event_questions');
    }
};
