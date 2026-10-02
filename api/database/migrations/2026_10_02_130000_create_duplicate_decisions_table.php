<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozhodnutia o duplicitách kanálov a miest pri importe.
 *
 * Slúži trojako: ako audit (čo sa s čím zlúčilo, prečo a s akou istotou), ako
 * cache (ten istý vstup sa nevyhodnocuje AI znova pri každom behu importu)
 * a ako front na kontrolu človekom — rozhodnutie `uncertain` vytvorí nový
 * záznam, ale zostane tu nepreskúmané, kým ho správca neoznačí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('duplicate_decisions')) {
            return;
        }

        Schema::create('duplicate_decisions', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 20);
            // Normalizovaný vstup (slug názvu + obec) — kľúč cache.
            $table->string('input_key', 250);
            $table->string('input_name', 250);
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->string('candidate_name', 250)->nullable();
            // Záznam, ktorý vznikol, keď sa nezlúčilo (uncertain / distinct).
            $table->unsignedBigInteger('created_id')->nullable();
            $table->string('decision', 20);
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('source', 10);
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['entity', 'input_key', 'candidate_id']);
            $table->index(['decision', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_decisions');
    }
};
