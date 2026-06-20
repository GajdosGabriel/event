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
        Schema::create('canals', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('municipality_id')->unsigned()->default(4209); // Default pre celé Slovensko
            $table->string('name',  250);
            $table->string('slug',  250);
            $table->string('title_prefix', 50)->nullable();
            $table->string('title_suffix', 50)->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('body')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))->default('published'); // ← status s default hodnotou
            $table->string('website',  150)->nullable();
            $table->string('registration_source')->default('self');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canals');
    }
};
