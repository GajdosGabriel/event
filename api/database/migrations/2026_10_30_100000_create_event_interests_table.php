<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Záujemcovia o podujatie, ktorým sa nedala vystaviť vstupenka (rezervácia
 * vypnutá, len platené lístky, plno). Doteraz po nich ostal len e-mail
 * organizátorovi a v dashboarde o nich nebolo ani stopy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_interests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 250)->nullable();
            $table->string('email', 190);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['event_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_interests');
    }
};
