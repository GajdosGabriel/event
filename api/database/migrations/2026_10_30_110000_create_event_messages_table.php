<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * História správ organizátora účastníkom — čo, komu a kedy odišlo. Bez nej by
 * si tím kanála nevedel overiť, či už ľudí o presune termínu niekto informoval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('kind', 20);
            $table->string('audience', 20);
            $table->string('subject', 190);
            $table->text('body')->nullable();
            $table->json('recipients');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_messages');
    }
};
