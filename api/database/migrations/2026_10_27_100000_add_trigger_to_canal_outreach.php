<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oslovenie viacerými triggermi (pred akciou, prah zobrazení, po akcii)
     * a cez Account Emailing (App\Services\Canals\CanalOutreachSender).
     *
     *  - `trigger` — ktorá udalosť oslovenie spustila (MailingTrigger),
     *  - `dedupe_key` — ten istý trigger k tej istej akcii len raz; podľa neho
     *    sa spárujú aj webhooky `mailing.delivery.*` z Accountu,
     *  - `account_queued` — koľko mailov Account k udalosti zaradil
     *    (0 = žiadna aktívna kampaň).
     */
    public function up(): void
    {
        Schema::table('canal_outreach', function (Blueprint $table) {
            if (! Schema::hasColumn('canal_outreach', 'trigger')) {
                $table->string('trigger', 32)->default('canal_event_finished')->after('event_id');
            }

            if (! Schema::hasColumn('canal_outreach', 'dedupe_key')) {
                $table->string('dedupe_key', 120)->nullable()->unique()->after('trigger');
            }

            if (! Schema::hasColumn('canal_outreach', 'account_queued')) {
                $table->unsignedSmallInteger('account_queued')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('canal_outreach', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn(['trigger', 'dedupe_key', 'account_queued']);
        });
    }
};
