<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oznamy a bannery zobrazované vo verejnom layoute.
 *
 * `variant` je kľúč farebnej schémy (`blue`, `green`…), nie hotové triedy —
 * Tailwind generuje CSS zo zdrojákov, takže trieda uložená len v databáze by
 * v builde neexistovala. Mapovanie kľúč → vzhľad je v `ui/src/styles.css`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default(ModelStatus::Published->value)->index();
            $table->string('placement', 32)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('variant', 32)->default('blue');
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('published_from')->nullable();
            $table->dateTime('published_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
