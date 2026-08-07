<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatická pripomienka účastníkom pred akciou (roadmap 3.5).
 *
 * `reminder_hours_before` je voľba organizátora (null = nič neposielať),
 * `reminder_sent_at` je poistka proti dvojitému odoslaniu — príkaz beží každých
 * desať minút a bez nej by pri každom behu poslal ďalšiu vlnu e-mailov.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_hours_before')->nullable()->after('workshop_lock_on_start');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_hours_before');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['reminder_hours_before', 'reminder_sent_at']);
        });
    }
};
