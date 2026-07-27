<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Priradenie štítkov k podujatiam.
     *
     * `source` rozhoduje, kto riadok vlastní: preštítkovanie cez AI maže a znovu
     * zakladá výhradne riadky so source='ai', takže ručný zásah človeka nikdy
     * neprepíše. `confidence` drží istotu modelu (ručné priradenie = 100).
     */
    public function up(): void
    {
        Schema::create('event_tag', function (Blueprint $table) {
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('tag_id');
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->string('source', 8)->default('manual');
            $table->timestamps();

            $table->primary(['event_id', 'tag_id']);
            $table->index('tag_id');

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tag');
    }
};
