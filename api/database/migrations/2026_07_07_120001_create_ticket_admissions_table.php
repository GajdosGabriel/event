<?php

use App\Enums\AdmissionStatus;
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
        Schema::create('ticket_admissions', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('ticket_id');       // objednávka / registrácia
            $table->unsignedInteger('ticket_type_id')->nullable();
            $table->unsignedInteger('event_id');        // denormalizované pre rýchly sken pri vchode
            $table->string('attendee_name', 250)->nullable();
            $table->string('qr_token', 64)->unique();
            $table->string('status', 20)->default(AdmissionStatus::Valid->value);
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedInteger('checked_in_by')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['event_id', 'status']);
            $table->index(['ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_admissions');
    }
};
