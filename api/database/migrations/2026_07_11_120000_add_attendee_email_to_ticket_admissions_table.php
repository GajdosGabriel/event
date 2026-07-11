<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->string('attendee_email', 190)->nullable()->after('attendee_name');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->dropColumn('attendee_email');
        });
    }
};
