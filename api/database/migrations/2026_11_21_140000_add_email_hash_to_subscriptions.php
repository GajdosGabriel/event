<?php

use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Odtlačok adresy, ktorý prežije odhlásenie.
     *
     * Odhlásenie adresu maže (Subscription::unsubscribe), takže bez odtlačku
     * by automatické sledovanie organizátora po ďalšej rezervácii človeka,
     * ktorý sa výslovne odhlásil, prihlásilo znova. Hash sa nedá spätne
     * prečítať ako adresa — slúži len na otázku „odhlásila sa už táto adresa?".
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->char('email_hash', 64)->nullable()->after('email');
            $table->index(['subscribable_type', 'subscribable_id', 'email_hash'], 'subscriptions_target_hash_index');
        });

        DB::table('subscriptions')->whereNotNull('email')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('subscriptions')->where('id', $row->id)->update([
                    'email_hash' => Subscription::hashEmail((string) $row->email),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_target_hash_index');
            $table->dropColumn('email_hash');
        });
    }
};
