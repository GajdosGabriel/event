<?php

use App\Models\User;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Správa kanála oddelená od jeho pôvodu (viď CanalStewardship).
 *
 * `registration_source` hovorí, ako kanál vznikol, a nemení sa. Či za ním
 * reálne niekto stojí, hovorí odteraz aj `claimed_at` — importovaný kanál,
 * ktorý prevzal organizátor, sa správa ako registrovaný.
 *
 * Backfill: importované kanály, na ktoré už niekto prijal pozvánku vlastníka,
 * sa označia za prevzaté a technický vlastník (super-admin z importu) sa z nich
 * odoberie — rovnako, ako to po novom spraví CanalStewardship::complete().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('canals', 'claimed_at')) {
            Schema::table('canals', function (Blueprint $table) {
                $table->timestamp('claimed_at')->nullable()->after('registration_source');
                $table->unsignedInteger('claimed_by_user_id')->nullable()->after('claimed_at');

                $table->foreign('claimed_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('canals', 'claimed_at')) {
            return;
        }

        Schema::table('canals', function (Blueprint $table) {
            $table->dropForeign(['claimed_by_user_id']);
            $table->dropColumn(['claimed_at', 'claimed_by_user_id']);
        });
    }

    private function backfill(): void
    {
        // Technický vlastník = super-admin s najnižším id, rovnako ako
        // ImportedCanalManager::systemOwner().
        $systemOwnerId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super-admin')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->min('model_has_roles.model_id');

        $claims = DB::table('canal_invitations')
            ->join('canals', 'canals.id', '=', 'canal_invitations.canal_id')
            ->join('canal_user', function ($join) {
                $join->on('canal_user.canal_id', '=', 'canal_invitations.canal_id')
                    ->on('canal_user.user_id', '=', 'canal_invitations.accepted_by_user_id');
            })
            ->where('canals.registration_source', 'import')
            ->whereNull('canals.claimed_at')
            ->where('canal_invitations.role', 'owner')
            ->whereNotNull('canal_invitations.accepted_at')
            ->whereNotNull('canal_invitations.accepted_by_user_id')
            ->where('canal_user.is_owner', true)
            ->when($systemOwnerId !== null, fn ($q) => $q->where('canal_invitations.accepted_by_user_id', '<>', $systemOwnerId))
            ->orderBy('canal_invitations.accepted_at')
            ->get(['canal_invitations.canal_id', 'canal_invitations.accepted_by_user_id', 'canal_invitations.accepted_at'])
            // Prvá prijatá pozvánka je okamih prevzatia.
            ->unique('canal_id');

        foreach ($claims as $claim) {
            DB::transaction(function () use ($claim, $systemOwnerId) {
                DB::table('canals')->where('id', $claim->canal_id)->update([
                    'claimed_at' => $claim->accepted_at,
                    'claimed_by_user_id' => $claim->accepted_by_user_id,
                ]);

                // Záznam do denníka ako pri živom prevzatí (CanalAuditor::claimed),
                // len bez e-mailu — prevzatie sa stalo dávno.
                Recorder::info('canals', 'claimed', 'Kanál #'.$claim->canal_id.' prevzatý (doplnené migráciou)',
                    status: 'ok',
                    userId: (int) $claim->accepted_by_user_id,
                    context: [
                        'canal_id' => (int) $claim->canal_id,
                        'member_id' => (int) $claim->accepted_by_user_id,
                        'removed_technical_owner_id' => $systemOwnerId,
                        'backfill' => true,
                    ],
                );

                if ($systemOwnerId === null) {
                    return;
                }

                DB::table('canal_user')
                    ->where('canal_id', $claim->canal_id)
                    ->where('user_id', $systemOwnerId)
                    ->delete();

                // Aktívny kanál super-admina nesmie ukazovať na kanál, kde už nie je.
                $activeCanalId = DB::table('users')->where('id', $systemOwnerId)->value('canal_id');

                if ((int) $activeCanalId === (int) $claim->canal_id) {
                    DB::table('users')->where('id', $systemOwnerId)->update([
                        'canal_id' => DB::table('canal_user')->where('user_id', $systemOwnerId)->min('canal_id'),
                    ]);
                }
            });
        }
    }
};
