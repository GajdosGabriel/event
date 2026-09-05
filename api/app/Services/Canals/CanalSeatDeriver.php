<?php

namespace App\Services\Canals;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Municipality;
use Illuminate\Support\Facades\DB;

/**
 * Doplní importovanému kanálu obec podľa toho, kde sa jeho podujatia konajú.
 *
 * Import zakladá kanál skôr, než vie čokoľvek o mieste — `resolveOrCreate()`
 * dostane len názov organizátora a zdrojovú doménu, takže obec musí ostať na
 * zbernom „Celé Slovensko". Tá hodnota ale nikdy nikto nedoplnil, takže filter
 * obce nad organizátormi (Canal::scopeByMunicipality číta `municipality_id`
 * priamo z kanála) nemal čo vrátiť.
 *
 * Sídlo sa preto odvodzuje z miest jeho podujatí. Pravidlo je zámerne prísne:
 * obec sa nastaví, len keď všetky podujatia kanála ukazujú na jednu jedinú.
 * Zberný kanál na zdroj (vyveska.sk má podujatia v 33 obciach) v „Celé
 * Slovensko" ostane — a to je preň správna odpoveď, nie chýbajúci údaj.
 */
class CanalSeatDeriver
{
    /**
     * @return bool  true, keď sa obec naozaj zmenila
     */
    public function sync(Canal $canal): bool
    {
        if (! $this->isDerivable($canal)) {
            return false;
        }

        $municipalityId = $this->soleEventMunicipality($canal);

        if ($municipalityId === null || $municipalityId === (int) $canal->municipality_id) {
            return false;
        }

        // forceFill + save namiesto update(): obec je odvodený údaj, nemá
        // prejsť cez observery ani hýbať ničím iným než sebou.
        $canal->forceFill(['municipality_id' => $municipalityId])->save();

        return true;
    }

    /**
     * Odvodzuje sa len tam, kde nie je čo pokaziť.
     *
     * Ručne zadanú obec neprepisujeme nikdy: `CanalStoreRequest` ju vyžaduje,
     * takže organizátor, ktorý si v dashboarde vybral „Celé Slovensko", to
     * myslel vážne. Preto len importované kanály, a len kým sedia na zbernej
     * hodnote.
     *
     * Cena za to je, že raz odvodená obec sa už nehýbe. Kanál, ktorý mal pri
     * prvom behu jediné podujatie v Bratislave a až neskôr sa rozrástol do
     * ďalších miest, ostane sedieť na Bratislave namiesto „Celé Slovensko".
     * Zámerne: alternatíva — prepočítať vždy — by pri každom nočnom importe
     * prepísala aj obec, ktorú admin opravil ručne. Keby to raz prekážalo,
     * riešením je stĺpec s pôvodom hodnoty (ako `coordinates_source` na tej
     * istej tabuľke), nie zrušenie tejto podmienky.
     */
    private function isDerivable(Canal $canal): bool
    {
        if ($canal->registration_source !== RegistrationSource::IMPORT) {
            return false;
        }

        $nationwideId = Municipality::nationwideId();

        return $nationwideId !== null && (int) $canal->municipality_id === $nationwideId;
    }

    /**
     * Obec, na ktorú ukazujú všetky podujatia kanála — alebo null, keď ich je
     * viac, žiadna, alebo sú všetky samy v „Celé Slovensko".
     */
    private function soleEventMunicipality(Canal $canal): ?int
    {
        $nationwideId = Municipality::nationwideId();

        $municipalities = DB::table('events')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('events.canal_id', $canal->id)
            ->whereNull('events.deleted_at')
            ->whereNull('venues.deleted_at')
            ->when($nationwideId !== null, fn ($query) => $query->where('venues.village_id', '<>', $nationwideId))
            // Dva stačia na rozhodnutie „je ich viac"; zvyšok by sa ťahal zbytočne.
            ->distinct()
            ->limit(2)
            ->pluck('venues.village_id');

        return $municipalities->count() === 1 ? (int) $municipalities->first() : null;
    }
}
