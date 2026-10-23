<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kam sa zlúčený kanál zlúčil.
 *
 * Názov zlúčenej duplicity je alias kanála, ktorý ostal: keď AI v ďalšom
 * článku pomenuje organizátora rovnako ako kedysi („TV LUX"), import má
 * podujatie dať kanálu „Televízia LUX", nie založiť „TV LUX" znova (viď
 * App\Services\Imports\DuplicateResolver::canalByKey()).
 *
 * Doplní sa aj pre zlúčenia z 2026_09_22_230000_merge_duplicate_organization_canals.php.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonický_id, [duplicitné_id, ...]] z 2026_09_22_230000
     */
    private const EARLIER_MERGES = [
        [789, [265]], [331, [679, 207]], [467, [450]], [36, [246]], [406, [854]], [568, [253]],
        [719, [577]], [63, [837]], [202, [938]], [926, [584, 595]], [566, [213]], [686, [355, 912, 233]],
        [903, [104]], [206, [223]], [602, [526, 132]], [675, [184, 440]], [775, [581]], [887, [317]],
        [680, [28]], [717, [389, 332]], [20, [14]], [902, [88]], [360, [976]], [1012, [530]],
        [698, [169, 356, 720, 738, 922]], [1031, [400]], [707, [617, 941, 414, 820, 793, 906, 447]],
        [302, [461, 783, 785]], [151, [408]], [688, [622]],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals') || Schema::hasColumn('canals', 'merged_into_id')) {
            return;
        }

        Schema::table('canals', function (Blueprint $table): void {
            $table->unsignedInteger('merged_into_id')->nullable()->after('identity_mode')->index();
        });

        foreach (self::EARLIER_MERGES as [$canonicalId, $duplicateIds]) {
            DB::table('canals')
                ->whereIn('id', $duplicateIds)
                ->whereNotNull('deleted_at')
                ->whereNull('merged_into_id')
                ->update(['merged_into_id' => $canonicalId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('canals', 'merged_into_id')) {
            Schema::table('canals', function (Blueprint $table): void {
                $table->dropIndex(['merged_into_id']);
                $table->dropColumn('merged_into_id');
            });
        }
    }
};
