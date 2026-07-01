<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TicketStatus;
use App\Enums\TicketPaymentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('holder_name', 250);
            $table->string('holder_email', 190);
            $table->string('holder_phone', 30)->nullable();
            $table->string('status', 20)->default(TicketStatus::Reserved->value);
            $table->string('payment_status', 20)->default(TicketPaymentStatus::None->value);
            $table->unsignedInteger('price_amount')->nullable();
            $table->char('price_currency', 3)->nullable();
            $table->string('qr_token', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedInteger('checked_in_by')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['event_id', 'status']);
            $table->index('holder_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
