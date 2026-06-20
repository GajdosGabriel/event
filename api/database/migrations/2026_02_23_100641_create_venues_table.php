<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('village_id')->unsigned();
            $table->string('name',  250);
            $table->string('street',  250)->nullable();
            $table->string('postcode',  250)->nullable();
            $table->string('slug',  250);
            $table->text('body')->nullable();
            $table->string('website',  150)->nullable();
            $table->string('country', 100)->default('Slovakia');

            // zemepisná šírka a dĺžka
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();


            $table->integer('capacity')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('category', 100)->nullable();

            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))->default('draft');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
