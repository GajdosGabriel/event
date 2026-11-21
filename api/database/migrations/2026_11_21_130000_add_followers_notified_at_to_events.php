<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kedy sa akcia oznámila sledovateľom kanála (app:canal-followers-notify).
     * Poistka proti druhému e-mailu, keď organizátor akciu stiahne a znova
     * zverejní.
     *
     * Všetko, čo už je zverejnené, sa označí ako oznámené: inak by prvý beh
     * príkazu po nasadení rozoslal celý katalóg naraz.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('followers_notified_at')->nullable()->after('published_at');
        });

        DB::table('events')
            ->whereNotNull('published_at')
            ->update(['followers_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('followers_notified_at');
        });
    }
};
