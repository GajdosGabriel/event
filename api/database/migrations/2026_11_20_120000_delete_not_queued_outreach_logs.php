<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Z histórie kanálov zmaže záznamy „ponuka prevzatia nezaradená…"
 * (`canals.outreach_not_queued`). Kým kampaň nebola aktívna, zapisovali sa
 * každý deň znova a nič nehovorili; nové sa už nezapisujú (ReportCanalOutreach).
 * Stav oslovenia ostáva v `canal_outreach`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_logs')->where('event', 'canals.outreach_not_queued')->delete();
    }

    public function down(): void
    {
        // Zmazaný balast sa neobnovuje.
    }
};
