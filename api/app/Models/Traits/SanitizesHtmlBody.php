<?php

namespace App\Models\Traits;

use App\Services\Imports\HtmlBodyCleaner;

/**
 * Prečistí HTML popis pri zápise do modelu.
 *
 * `body` sa vo verejnom UI vykresľuje cez `v-html`, takže čokoľvek, čo sa doň
 * dostane, sa v prehliadači návštevníka aj vykoná. Čistenie je preto na
 * mutatore, nie vo FormRequeste: rovnaký stĺpec plnia aj importy, AI popisy
 * z plagátu a admin nástroje, a všetky tieto cesty musia skončiť rovnako.
 *
 * Čistí sa pri zápise, nie pri čítaní — výpis podujatí by inak parsoval DOM
 * pre každý riadok.
 */
trait SanitizesHtmlBody
{
    public function setBodyAttribute(mixed $value): void
    {
        $this->attributes['body'] = static::sanitizeHtmlBody($value);
    }

    /**
     * NULL prežije ako NULL: „popis nie je" a „popis je prázdny reťazec" sú
     * pre stĺpec dve rôzne veci a čistenie ich nemá zlievať.
     */
    protected static function sanitizeHtmlBody(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $cleaner = app(HtmlBodyCleaner::class);

        // Popis bez jediného tagu je čistý text — a ten sa musí prevádzať cez
        // fromPlainText(), lebo HTML cesta zlieva biele znaky a prázdny riadok
        // medzi odstavcami by zmizol.
        return strip_tags($value) === $value
            ? $cleaner->fromPlainText($value)
            : $cleaner->cleanHtmlString($value);
    }
}
