<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The QR token + check-in now live on individual admissions
     * (ticket_admissions). Remove them from the order (tickets) row.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['checked_in_by']);
            $table->dropUnique('tickets_qr_token_unique');
            $table->dropColumn(['qr_token', 'checked_in_at', 'checked_in_by']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedInteger('checked_in_by')->nullable();
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
