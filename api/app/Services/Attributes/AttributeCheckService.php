<?php

namespace App\Services\Attributes;

use App\Contracts\AttributeProbe;
use App\Enums\AttributeCheckStatus;
use App\Models\AttributeCheck;
use App\Notifications\AttributeIssueNotice;
use App\Services\Attributes\Probes\WebsiteProbe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Overovanie hodnôt, ktoré môžu časom prestať fungovať — dnes webové adresy.
 *
 * Celý zmysel je v tom, že formulár na overenie nečaká. Organizátor uloží web
 * a ide ďalej; či adresa naozaj odpovedá, sa zisťuje až potom, na pozadí. Web
 * môže byť v momente ukladania na minútu vypnutý a bolo by absurdné kvôli tomu
 * neuložiť formulár — a rovnako absurdné je overiť adresu raz a už nikdy,
 * lebo odkazy neumierajú pri ukladaní, ale o dva roky.
 *
 * Tri spúšťače, jeden mechanizmus:
 *   1. zmena hodnoty (formulár, import, konzola) — overí sa čoskoro po uložení;
 *   2. klik návštevníka na verejnej stránke — pošle overenie na začiatok radu
 *      a zapamätá si, kde sa to stalo (viď BrokenLinkReportController);
 *   3. pravidelný obchôdzka — hodnota v poriadku sa preverí raz za mesiac.
 *
 * Sondy sú vymeniteľné (App\Contracts\AttributeProbe), takže ďalší overovaný
 * údaj znamená novú sondu a riadok v registri — nič viac.
 */
class AttributeCheckService
{
    /** @var array<string, AttributeProbe>|null */
    private ?array $probes = null;

    /**
     * Register sond. Kľúč je názov atribútu na modeli.
     *
     * @return array<string, AttributeProbe>
     */
    public function probes(): array
    {
        if ($this->probes === null) {
            $this->probes = [];

            foreach ((array) config('attribute_checks.probes', [WebsiteProbe::class]) as $class) {
                /** @var AttributeProbe $probe */
                $probe = app($class);
                $this->probes[$probe->attribute()] = $probe;
            }
        }

        return $this->probes;
    }

    public function probeFor(string $attribute): ?AttributeProbe
    {
        return $this->probes()[$attribute] ?? null;
    }

    /** Dá sa táto hodnota tohto modelu overovať? */
    public function supports(Model $model, string $attribute): bool
    {
        return AttributeCheck::aliasFor($model) !== null
            && method_exists($model, 'checkedAttributes')
            && in_array($attribute, $model->checkedAttributes(), true)
            && $this->probeFor($attribute) !== null;
    }

    /**
     * Zosúladí evidenciu so stavom modelu — volá sa po každom uložení
     * (HasCheckedAttributes).
     *
     * Robí len to nutné: prázdna hodnota riadok zmaže, nezmenená ho nechá tak
     * (aby uloženie formulára nezhodilo rozbehnutý cyklus opakovaní) a zmenená
     * ho postaví na začiatok — starý výsledok patril inej adrese.
     */
    public function sync(Model $model): void
    {
        if (! method_exists($model, 'checkedAttributes')) {
            return;
        }

        foreach ($model->checkedAttributes() as $attribute) {
            if (! $this->supports($model, $attribute)) {
                continue;
            }

            $value = $this->valueOf($model, $attribute);
            $existing = $model->attributeChecks()->where('attribute', $attribute)->first();

            if ($value === null) {
                $existing?->delete();

                continue;
            }

            if ($existing && $existing->matches($value)) {
                continue;
            }

            $model->attributeChecks()->updateOrCreate(
                ['attribute' => $attribute],
                [
                    'value' => $value,
                    'status' => AttributeCheckStatus::Pending,
                    'failures' => 0,
                    'http_status' => null,
                    'reason' => null,
                    'checked_at' => null,
                    // Nie hneď: uloženie formulára býva v sérii (organizátor
                    // dopĺňa údaje po častiach) a odchytiť až tú poslednú
                    // verziu je lacnejšie než overovať každý medzikrok.
                    'next_check_at' => now()->addMinutes(15),
                    'notified_at' => null,
                ],
            );
        }
    }

    /**
     * Hodnoty, ktoré čakajú na overenie.
     *
     * @return Collection<int, AttributeCheck>
     */
    public function due(int $limit): Collection
    {
        return AttributeCheck::query()
            ->due()
            ->with('checkable')
            // Podnet od návštevníka má prednosť — o ten odkaz niekto naozaj
            // stál, kým zvyšok je len plánovaná obchôdzka.
            ->orderByRaw('reported_at is null')
            ->oldest('next_check_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Overí jednu hodnotu a zapíše výsledok. Ak treba, ozve sa majiteľovi.
     *
     * @return bool `false`, keď sa neoverovalo (model medzitým zmizol alebo sa
     *              hodnota zmenila — riadok sa v oboch prípadoch upratal)
     */
    public function run(AttributeCheck $check): bool
    {
        $model = $check->checkable;
        $probe = $this->probeFor($check->attribute);

        if (! $model || ! $probe || ! $this->supports($model, $check->attribute)) {
            $check->delete();

            return false;
        }

        $value = $this->valueOf($model, $check->attribute);

        // Hodnota sa zmenila medzi výberom dávky a overením. Nový stav si
        // vypýta sync() pri najbližšom uložení; tento riadok už neplatí.
        if ($value === null || ! $check->matches($value)) {
            $this->sync($model);

            return false;
        }

        $result = $probe->probe($check->value);

        if ($result->skipped) {
            // Sonda si netrúfla. Stav ostáva nedotknutý a skúsi sa až
            // v riadnom termíne — inak by sa neoveriteľná hodnota točila
            // v dávke dokola a vytláčala z nej tie, ktoré overiť vieme.
            $check->forceFill([
                'next_check_at' => now()->addDays(max(1, (int) config('attribute_checks.recheck_ok_days', 30))),
            ])->save();

            return true;
        }

        $result->ok
            ? $this->recordSuccess($check, $result)
            : $this->recordFailure($check, $result, $model);

        return true;
    }

    /**
     * Podnet z verejnej stránky: niekto na odkaz klikol, over ho prednostne.
     *
     * Klik sám o sebe nie je dôkaz, že je odkaz pokazený — prehliadač odíde na
     * cudziu doménu a či sa tam niečo načítalo, sa z našej stránky zistiť nedá.
     * Je to len signál, že o odkaz je záujem, a teda že sa oplatí overiť ho
     * teraz a nie o týždeň. Rozhodne vždy až sonda.
     *
     * @param  string|null  $from  Cesta na našej stránke, kde odkaz visí
     * @return bool `false`, keď sa podnet zahodil (nepodporovaná hodnota alebo
     *              beží odstup od predošlého nahlásenia)
     */
    public function report(Model $model, string $attribute, ?string $from = null): bool
    {
        if (! $this->supports($model, $attribute)) {
            return false;
        }

        $value = $this->valueOf($model, $attribute);

        if ($value === null) {
            return false;
        }

        $check = $model->attributeChecks()->where('attribute', $attribute)->first();

        if ($check === null || ! $check->matches($value)) {
            $this->sync($model);
            $check = $model->attributeChecks()->where('attribute', $attribute)->first();
        }

        if ($check === null) {
            return false;
        }

        $cooldown = max(0, (int) config('attribute_checks.report_cooldown_minutes', 60));

        if ($check->reported_at !== null && $check->reported_at->diffInMinutes(now()) < $cooldown) {
            return false;
        }

        $check->forceFill([
            'reported_from' => $from,
            'reported_at' => now(),
            // Prednosť v rade; samotné poradie rieši due().
            'next_check_at' => now(),
        ])->save();

        return true;
    }

    private function recordSuccess(AttributeCheck $check, ProbeResult $result): void
    {
        $check->forceFill([
            'status' => AttributeCheckStatus::Ok,
            'failures' => 0,
            'http_status' => $result->httpStatus,
            'reason' => null,
            'checked_at' => now(),
            'next_check_at' => now()->addDays(max(1, (int) config('attribute_checks.recheck_ok_days', 30))),
            // Po oprave sa pri ďalšom výpadku ozveme znova bez čakania.
            'notified_at' => null,
            'reported_from' => null,
            'reported_at' => null,
        ])->save();
    }

    private function recordFailure(AttributeCheck $check, ProbeResult $result, Model $model): void
    {
        $failures = min(255, $check->failures + 1);

        $check->forceFill([
            'status' => AttributeCheckStatus::Failed,
            'failures' => $failures,
            'http_status' => $result->httpStatus,
            'reason' => $result->reason,
            'checked_at' => now(),
            'next_check_at' => $this->nextRetryAt($failures),
        ])->save();

        if ($this->shouldNotify($check)) {
            $this->notify($check, $model);
        }
    }

    private function nextRetryAt(int $failures): Carbon
    {
        /** @var array<int, int> $steps */
        $steps = (array) config('attribute_checks.retry_hours', [6, 24, 72, 168]);
        $steps = array_values(array_filter(array_map('intval', $steps), fn (int $h) => $h > 0)) ?: [24];

        // Posledný odstup platí aj pre všetky ďalšie pokusy — donekonečna
        // predlžovať nemá zmysel, mesačná obchôdzka je dolná hranica záujmu.
        $hours = $steps[min($failures, count($steps)) - 1];

        return now()->addHours($hours);
    }

    private function shouldNotify(AttributeCheck $check): bool
    {
        $threshold = max(1, (int) config('attribute_checks.failures_before_notice', 2));

        if ($check->failures < $threshold) {
            return false;
        }

        if ($check->notified_at === null) {
            return true;
        }

        $cooldown = max(0, (int) config('attribute_checks.notice_cooldown_days', 30));

        return $cooldown > 0 && $check->notified_at->addDays($cooldown)->isPast();
    }

    private function notify(AttributeCheck $check, Model $model): void
    {
        $recipient = method_exists($model, 'attributeIssueRecipient')
            ? $model->attributeIssueRecipient()
            : null;

        // Bez majiteľa sa nemá komu ozvať — typicky importovaný záznam
        // z cudzieho zdroja. Stav ostáva zapísaný, vidí ho admin.
        if ($recipient === null) {
            return;
        }

        $recipient->notify(new AttributeIssueNotice($check, $model));

        $check->forceFill(['notified_at' => now()])->save();
    }

    /** Aktuálna hodnota atribútu na modeli, alebo null keď je prázdna. */
    private function valueOf(Model $model, string $attribute): ?string
    {
        $value = $model->getAttribute($attribute);

        return filled($value) ? (string) $value : null;
    }
}
