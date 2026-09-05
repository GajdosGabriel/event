<?php

namespace App\Services\Canals;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Municipality;
use App\Services\Geocoding\MunicipalityGeocodeResolver;
use App\Support\PlaceholderNames;
use Illuminate\Support\Facades\DB;

/**
 * Doplní importovanému kanálu obec — buď z mesta organizátora prečítaného
 * z článku, alebo (keď také nie je) podľa toho, kde sa jeho podujatia konajú.
 *
 * Import zakladá kanál skôr, než vie čokoľvek o mieste — `resolveOrCreate()`
 * dostane len názov organizátora a zdrojovú doménu, takže obec musí ostať na
 * zbernom „Celé Slovensko". Tá hodnota ale nikdy nikto nedoplnil, takže filter
 * obce nad organizátormi (Canal::scopeByMunicipality číta `municipality_id`
 * priamo z kanála) nemal čo vrátiť.
 *
 * Prednosť má sídlo organizátora (`applyDetectedCity()`): „Západoslovenské
 * múzeum v Trnave" je údaj o organizátorovi, kým miesto konania je údaj
 * o jednom podujatí. Kým sa odvodzovalo výhradne z miest, stačilo, aby import
 * trafil zlé miesto — a kanál dostal obec, ktorá v článku ani nebola (múzeum
 * z Trnavy sedelo na Košiciach podľa rovnomenného kostola v inom meste).
 *
 * Odvodenie z miest (`sync()`) ostáva ako záloha. Pravidlo je zámerne prísne:
 * obec sa nastaví, len keď všetky podujatia kanála ukazujú na jednu jedinú.
 * Zberný kanál na zdroj (vyveska.sk má podujatia v 33 obciach) v „Celé
 * Slovensko" ostane — a to je preň správna odpoveď, nie chýbajúci údaj.
 */
class CanalSeatDeriver
{
    public function __construct(
        private readonly MunicipalityGeocodeResolver $municipalityGeocoder = new MunicipalityGeocodeResolver(),
    ) {}

    /**
     * Sídlo z mesta organizátora, ako ho z článku prečítal regex alebo AI.
     *
     * Mesto prichádza v podobe, v akej stálo v texte — teda aj skloňované
     * („v Trnave"). Preklad na obec z číselníka rieši `MunicipalityGeocodeResolver`:
     * najprv číselník, potom orezanie koncovky a až nakoniec geokóder.
     *
     * @return bool  true, keď sa obec naozaj zmenila
     */
    public function applyDetectedCity(Canal $canal, ?string $city): bool
    {
        $municipalityId = $this->detectedSeat($canal, $city);

        if ($municipalityId === null) {
            return false;
        }

        $canal->forceFill(['municipality_id' => $municipalityId])->save();

        return true;
    }

    /**
     * Obec, ktorú by `applyDetectedCity()` zapísala — alebo null, keď by
     * nezapísala nič. Oddelené preto, aby `app:backfill-canal-seats --dry-run`
     * hlásil skutočný výsledok, nie len to, že nejaké mesto našiel.
     */
    public function detectedSeat(Canal $canal, ?string $city): ?int
    {
        $city = is_string($city) ? trim($city) : '';

        // Zástupnú hodnotu ("null", "neuvedené") nemá zmysel prekladať na obec —
        // v poslednom kroku by na ňu resolver minul dotaz do siete.
        if ($city === '' || PlaceholderNames::matches($city)) {
            return null;
        }

        if ($canal->registration_source !== RegistrationSource::IMPORT) {
            return null;
        }

        // Právo zápisu sa overuje pred prekladom mesta: resolver siaha
        // v poslednom kroku na Nominatim a pýtať sa siete na hodnotu, ktorú
        // aj tak nesmieme zapísať, nemá zmysel.
        if (! $this->isOverwritable($canal)) {
            return null;
        }

        $resolved = $this->municipalityGeocoder->resolve($city);

        if ($resolved === null) {
            return null;
        }

        $municipalityId = (int) $resolved['village_id'];

        // Zberná hodnota nie je údaj o sídle — na ňu sa kanál neprepisuje.
        if ($municipalityId === Municipality::nationwideId() || $municipalityId === (int) $canal->municipality_id) {
            return null;
        }

        return $municipalityId;
    }

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
     * Smie sa už vyplnená obec prepísať sídlom organizátora?
     *
     * Ručne zadanú obec neprepisujeme nikdy — platí to isté, čo pri `sync()`.
     * Rozoznať ju bez stĺpca s pôvodom hodnoty sa dá jedine podľa toho, či
     * kanál sedí presne na tom, čo by odvodenie z miest vyrobilo samo: zberné
     * „Celé Slovensko" (tam ho postavil import), alebo obec jeho jediného
     * miesta konania (tam ho postavil `sync()`). Čokoľvek iné vybral človek.
     */
    private function isOverwritable(Canal $canal): bool
    {
        $nationwideId = Municipality::nationwideId();
        $current = (int) $canal->municipality_id;

        if ($nationwideId !== null && $current === $nationwideId) {
            return true;
        }

        return $current === $this->soleEventMunicipality($canal);
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
