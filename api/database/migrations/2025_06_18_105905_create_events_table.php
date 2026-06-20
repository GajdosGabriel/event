<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ModelStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            return;
        }

        Schema::create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('canal_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->integer('venue_id')->unsigned()->nullable();
            $table->string('name', 250);
            $table->string('slug', 250);
            $table->text('body')->nullable();
            $table->text('body_ai')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('registration_deadline_at')->nullable();
            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))->default('draft');
            $table->string('website', 150)->nullable();
            $table->string('orginal_source')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
