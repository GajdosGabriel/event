<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Stav hľadania náhradnej adresy jedného kanála (App\Services\Canals\CanalEmailFinder).
 */
class CanalEmailSearch extends Model
{
    public const FOUND = 'found';

    public const NONE = 'none';

    public const SKIPPED = 'skipped';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'searched_at' => 'datetime',
        'retry_at' => 'datetime',
    ];

    private static bool $ready = false;

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    /**
     * Kanál prišiel o poslednú použiteľnú adresu — na rad pôjde v najbližšom
     * behu app:canal-emails-find. Je to nová informácia, takže sa vynuluje
     * aj počet neúspešných pokusov.
     */
    public static function requestFor(Canal $canal): void
    {
        if (! (self::$ready = self::$ready || Schema::hasTable('canal_email_searches'))) {
            return;
        }

        self::query()->updateOrCreate(['canal_id' => $canal->id], ['requested_at' => now(), 'attempts' => 0, 'retry_at' => null]);
    }

    /**
     * Kanál dostal nový zdroj kontaktu (web) — ak sa mu už hľadalo bez
     * úspechu, pôjde na rad hneď, s novými pokusmi.
     */
    public static function retryFor(Canal $canal): void
    {
        if (! (self::$ready = self::$ready || Schema::hasTable('canal_email_searches'))) {
            return;
        }

        self::query()->where('canal_id', $canal->id)->where('result', '!=', self::FOUND)
            ->update(['attempts' => 0, 'retry_at' => null]);
    }
}
