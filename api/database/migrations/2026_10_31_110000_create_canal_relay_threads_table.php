<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canal_relay_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('target_name');
            $table->string('asker_name');
            $table->string('asker_email');
            $table->string('organizer_email');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_relay_threads');
    }
};
