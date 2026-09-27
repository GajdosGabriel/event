<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_enrichments', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->unique(['subject_type', 'subject_id']);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->json('changes')->nullable();
            $table->json('evidence')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_completed_at')->nullable();
            $table->timestamp('notification_retry_at')->nullable();
            $table->unsignedTinyInteger('notification_attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_enrichments');
    }
};
