<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kedy kontakt nespravovaného kanála dostal technický e-mail o zverejnení
 * tejto akcie s ponukou prevzatia (CanalEventOffer). Opätovné zverejnenie
 * e-mail nezopakuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('takeover_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('takeover_notified_at');
        });
    }
};
