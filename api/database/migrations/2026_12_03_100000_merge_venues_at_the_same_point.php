<?php

use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;

/**
 * Miesta na tom istom bode s takmer rovnakým názvom (kontrola 26. 11. 2026,
 * pravidlo DuplicateResolver::SAME_POINT_NAME_JACCARD): #929 Evanjelický kostol
 * (Banská Bystrica), archivovaný, → #1094 Evanjelický chrám Boží, Lazovná 268/46.
 *
 * #993 Evanjelický boží chrám Košice-Terasa mal súradnice kostola na Mlynskej
 * v centre; patrí mu Toryská 206/1G (OpenStreetMap).
 */
return new class extends Migration
{
    public function up(): void
    {
        $source = Venue::query()->find(929);
        $target = Venue::query()->find(1094);

        if ($source && $target && $source->name === 'Evanjelický kostol (Banská Bystrica)'
            && $target->name === 'Evanjelický chrám Boží (Banská Bystrica)' && $target->status !== ModelStatus::Archived) {
            try {
                app(ResourceMerger::class)->merge('venues', 929, 1094, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', 'Zlúčenie miesta #929 → #1094 preskočené',
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }

        $terasa = Venue::query()->find(993);
        if ($terasa && $terasa->name === 'Evanjelický boží chrám Košice-Terasa'
            && abs((float) $terasa->latitude - 48.7210489) < 0.0001) {
            $terasa->forceFill(['latitude' => 48.7134248, 'longitude' => 21.2304711, 'coordinates_source' => 'venue'])->save();
            Recorder::info('venues', 'profile_described', "Miesto {$terasa->name}: opravené súradnice",
                status: 'ok', subject: $terasa, context: ['fields' => ['latitude', 'longitude']]);
        }
    }

    public function down(): void
    {
        // Zlúčenie a oprava súradníc sa nevracajú.
    }
};
