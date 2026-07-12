<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Polymorfný cieľ správy — podujatie, miesto, kanál… (morph map:
            // 'event' / 'venue' / 'canal'). Pridanie ďalšieho typu nevyžaduje
            // zmenu schémy, len jeho zaradenie do whitelistu a Messageable.
            $table->string('messageable_type');
            $table->unsignedInteger('messageable_id');
            // Odosielateľ aj príjemca sú používatelia. Účet odosielateľa vzniká
            // aj z hosťovskej správy (neaktívny, ako pri vstupenkách), preto FK
            // pri zmazaní účtu len vynulujeme a správu zachováme.
            $table->unsignedInteger('sender_user_id')->nullable();
            $table->unsignedInteger('recipient_user_id')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('sender_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['messageable_type', 'messageable_id']);
            $table->index(['recipient_user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
