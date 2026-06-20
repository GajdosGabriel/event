<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, File, Schema};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vytvorenie tabuľky (ak ešte neexistuje)
        if (!Schema::hasTable('municipalities')) {
            Schema::create('municipalities', function (Blueprint $table) {
                $table->increments('id');
                $table->string('fullname');
                $table->string('shortname');
                $table->string('zip', 6);
                $table->integer('district_id');
                $table->integer('region_id');
                $table->boolean('use')->default(true);
                $table->timestamps();
            });
        }

        // Cesta k SQL súboru
        $sqlFile = database_path('./municipalities.sql');

        if (File::exists($sqlFile)) {
            // Priamy import SQL
            DB::unprepared(File::get($sqlFile));

            // Alebo premenujte pôvodnú tabuľku villages na municipalities
            // DB::statement('RENAME TABLE villages TO municipalities');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
