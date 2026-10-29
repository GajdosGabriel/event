<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexy pre vyhľadávanie podľa e-mailu.
 *
 * AccountMailing::contact() hľadá oslovenie podľa adresy pri každom kontakte
 * (aj v hromadnom account:sync-mailing-contacts) a TicketOwnership vstupenky
 * účastníka podľa adresy. Kolácia *_ci porovnáva bez ohľadu na veľkosť písmen,
 * takže index sa použije priamo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canal_outreach', function (Blueprint $table) {
            $table->index('email');
        });

        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->index('attendee_email');
        });
    }

    public function down(): void
    {
        Schema::table('canal_outreach', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });

        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->dropIndex(['attendee_email']);
        });
    }
};
