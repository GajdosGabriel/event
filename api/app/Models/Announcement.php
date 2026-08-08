<?php

namespace App\Models;

use App\Enums\AnnouncementPlacement;
use App\Enums\AnnouncementVariant;
use App\Enums\ModelStatus;
use App\Models\Traits\SanitizesHtmlBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Oznam / banner vo verejnom layoute.
 *
 * Vypnutie oznamu je zmena stavu na `draft`, nie mazanie — text ostane uložený
 * a rovnaká kampaň sa dá o mesiac zapnúť späť jedným klikom.
 */
class Announcement extends Model
{
    use SanitizesHtmlBody, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
        'placement' => AnnouncementPlacement::class,
        'variant' => AnnouncementVariant::class,
        'published_from' => 'datetime',
        'published_until' => 'datetime',
    ];

    /**
     * Čo smie vidieť neprihlásený návštevník: publikované a v okne zobrazovania.
     * Prázdny dátum znamená „bez obmedzenia", nie „nikdy".
     */
    public function scopeActiveForPublic(Builder $query): Builder
    {
        return $query
            ->where('status', ModelStatus::Published->value)
            ->where(function (Builder $query) {
                $query->whereNull('published_from')
                    ->orWhere('published_from', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('published_until')
                    ->orWhere('published_until', '>=', now());
            });
    }
}
