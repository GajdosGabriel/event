<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podpora — prihlásený používateľ napíše administrátorom platformy a odpoveď
 * dostane priamo v sekcii Správy (a e-mailom).
 *
 * Vlákno, nie jednorazový formulár: „nejde mi to" takmer vždy znamená
 * doplňujúcu otázku a tá sa nesmie stratiť v cudzej e-mailovej schránke.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            // Kontext, v ktorom človek písal — pri „nevidím svoje podujatie" to rozhoduje.
            $table->unsignedInteger('canal_id')->nullable();
            $table->string('category', 32);
            $table->string('subject', 150);
            $table->string('status', 16)->default('open')->index();
            $table->string('page_url', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            // Kedy strana naposledy videla vlákno; null = čaká na ňu niečo nové.
            // Odpoveď jednej strany vynuluje príznak druhej.
            $table->timestamp('user_seen_at')->nullable();
            $table->timestamp('staff_seen_at')->nullable();
            $table->timestamps();

            // users/canals majú `increments` (unsigned int), nie bigint — preto
            // stĺpce aj cudzie kľúče ručne, nie cez foreignId().
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('canal_id')->references('id')->on('canals')->nullOnDelete();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            // Príznak, nie odvodenie z roly: super-admin, ktorý rieši vlastný
            // problém, je v tom vlákne používateľ.
            $table->boolean('is_staff')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
