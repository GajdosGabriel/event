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
        if (Schema::hasTable('organizations')) {
            return;
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('village_id')->nullable();
            $table->boolean('person')->default(false);
            $table->string('avatar', 200)->nullable();
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->string('street', 191)->nullable();
            $table->integer('psc')->nullable();
            $table->string('email', 100)->nullable();
            $table->mediumText('description')->nullable();
            $table->string('mod_title', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_numeric', 20)->nullable();
            $table->string('youtube_channel', 40)->nullable();
            $table->string('youtube_playlist', 40)->nullable();
            $table->string('website', 150)->nullable();
            $table->boolean('published')->default(true);
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
        Schema::dropIfExists('organizations');
    }
};
