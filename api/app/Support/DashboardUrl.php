<?php

namespace App\Support;

use App\Models\AttributeCheck;
use Illuminate\Database\Eloquent\Model;

/**
 * Adresy do dashboardu pre odkazy v e-mailoch („tu to opravíte").
 *
 * Oddelené od PublicUrl zámerne: to skladá verejné adresy pre vyhľadávače
 * a canonical, kým tieto vedú za prihlásenie a nesmú skončiť v sitemape.
 */
final class DashboardUrl
{
    /** Segment výpisu pre daný alias modelu (viď AttributeCheck::TARGETS). */
    private const SEGMENTS = [
        'event' => 'events',
        'canal' => 'canals',
        'venue' => 'venues',
        'organization' => 'organizations',
    ];

    public static function base(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/dashboard';
    }

    /** Formulár úprav daného záznamu, alebo null pri neznámom type. */
    public static function edit(Model $model): ?string
    {
        $alias = AttributeCheck::aliasFor($model);
        $segment = $alias === null ? null : (self::SEGMENTS[$alias] ?? null);

        if ($segment === null || $model->getKey() === null) {
            return null;
        }

        return self::base().'/'.$segment.'/'.$model->getKey().'/edit';
    }
}
