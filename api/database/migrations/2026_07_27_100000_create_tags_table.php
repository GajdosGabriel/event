<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Číselník obsahových štítkov podujatí.
     *
     * Štítky sú rozdelené do facetov (`group`): forma, téma, publikum, charakter.
     * Podujatie dostane štítky z viacerých facetov naraz — „koncert" a „folklór"
     * nie sú alternatívy, ale dve rôzne osi toho istého podujatia. Pri neskorších
     * odporúčaniach sa každý facet váži zvlášť.
     *
     * `group` je zámerne string, nie enum: nový facet má byť riadok v seedri,
     * nie migrácia. Na strane kódu ho kryje App\Enums\TagGroup.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('group', 16);
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('emoji', 8)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
