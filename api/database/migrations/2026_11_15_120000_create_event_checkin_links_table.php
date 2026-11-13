<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odkaz na skener pre dobrovoľníkov pri vchode (CheckinLink).
 *
 * `token` v adrese `/vstup/{token}` je autorizácia — rovnaká konvencia ako
 * nástenka otázok `/q/{token}`. Platí len pre jedno podujatie a len do
 * `expires_at`; organizátor ho môže kedykoľvek zrušiť (`revoked_at`).
 *
 * `checked_in_link_id` pri vstupenke drží, ktorý odkaz ju pustil — pri dvoch
 * dobrovoľníkoch na dverách je „už prišiel, pustil ho Vchod A" užitočnejšie
 * než holé „už prišiel".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_checkin_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->string('token', 40)->unique();
            $table->string('label', 80)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->foreignId('checked_in_link_id')->nullable()->after('checked_in_by')
                ->constrained('event_checkin_links')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_link_id');
        });

        Schema::dropIfExists('event_checkin_links');
    }
};
