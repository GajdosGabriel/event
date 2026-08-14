<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preukázateľnosť súhlasu s obchodnými podmienkami (čl. 7 ods. 1 GDPR):
 * ku každému účtu si držíme, KEDY bol súhlas udelený a s AKOU verziou
 * dokumentov — samotné zaškrtnutie políčka bez týchto dvoch údajov nie je
 * po zmene textov ničím podložené.
 *
 * Rovnaké stĺpce má aj `pending_registrations`, lebo účet vzniká až po
 * overení e-mailu a súhlas treba preniesť z čakajúcej registrácie.
 *
 * Existujúce účty ostávajú s NULL — spätne za nich nikto nesúhlasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'terms_accepted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
                $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
            });
        }

        if (! Schema::hasColumn('pending_registrations', 'terms_accepted_at')) {
            Schema::table('pending_registrations', function (Blueprint $table) {
                $table->timestamp('terms_accepted_at')->nullable()->after('registered_via');
                $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'terms_accepted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['terms_accepted_at', 'terms_version']);
            });
        }

        if (Schema::hasColumn('pending_registrations', 'terms_accepted_at')) {
            Schema::table('pending_registrations', function (Blueprint $table) {
                $table->dropColumn(['terms_accepted_at', 'terms_version']);
            });
        }
    }
};
