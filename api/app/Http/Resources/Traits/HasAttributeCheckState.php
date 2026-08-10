<?php

namespace App\Http\Resources\Traits;

use App\Models\AttributeCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Stav overenia hodnôt pre front — „táto adresa nám neodpovedá".
 *
 * Chodí len na detaile a len tomu, kto smie záznam upravovať. Nie preto, že by
 * to bolo tajomstvo, ale preto, že je to informácia na konanie: návštevníkovi
 * je nanič a vo výpise by za každý riadok pribudol dopyt navyše (rovnaká
 * úvaha ako pri `contactable` v resource).
 *
 * `pending` sa nevracia vôbec — „zatiaľ sme neoverili" nie je čo zobrazovať
 * a v UI by z toho bola blikajúca značka pri každom novom zázname.
 */
trait HasAttributeCheckState
{
    /** @return array<string, array<string, mixed>>|null */
    protected function attributeCheckState(Request $request): ?array
    {
        $model = $this->resource;

        if (! $model instanceof Model || AttributeCheck::aliasFor($model) === null) {
            return null;
        }

        if ($request->route()?->getActionMethod() !== 'show') {
            return null;
        }

        if (! ($request->user()?->can('update', $model) ?? false)) {
            return null;
        }

        $state = [];

        foreach ($model->attributeChecks as $check) {
            if (! $check->status->isFailed()) {
                continue;
            }

            $state[$check->attribute] = [
                'status' => $check->status->value,
                'reason' => $check->reason,
                'http_status' => $check->http_status,
                'failures' => $check->failures,
                'checked_at' => $check->checked_at?->toIso8601String(),
                // Kedy sme sa ozvali — front tak vie povedať „už sme vám
                // o tom písali" namiesto ďalšieho výkričníka bez kontextu.
                'notified_at' => $check->notified_at?->toIso8601String(),
            ];
        }

        return $state === [] ? null : $state;
    }
}
