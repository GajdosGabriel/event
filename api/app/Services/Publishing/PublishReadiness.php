<?php

namespace App\Services\Publishing;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/**
 * „Je záznam hotový na zverejnenie?" — jedna odpoveď pre formulár aj server.
 *
 * Otázka vzniká na dvoch miestach a musí mať tú istú odpoveď:
 *
 *  - vo formulári, priebežne pri písaní — riadi ukazovateľ pripravenosti
 *    a odomyká panel „Vyplniť pomocou AI" (viď usePublishReadiness.ts),
 *  - na serveri, pri zverejnení — rozhoduje, či sa obsah pošle na kontrolu
 *    (viď App\Services\Content\ContentReviewService).
 *
 * Preto tu nie sú žiadne pravidlá, len ich vyhodnotenie: zoznam podmienok je
 * v `config/content_review.php` a prehliadač si ten istý zoznam stiahne cez
 * `GET {scope}/publish-readiness`. Kým bola tá istá otázka napísaná dvakrát,
 * v UI a na serveri, ticho si odpovedali inak.
 *
 * Vedome to NIE je validácia: nepripravený záznam sa zverejniť dá. Toto je
 * odporúčanie a merítko, nie zámok — organizátor, ktorý vie, čo robí, nemá
 * naraziť na dvere.
 */
class PublishReadiness
{
    /** Alias typu záznamu podľa triedy modelu — kľúč do konfigurácie. */
    private const ALIASES = [
        Event::class => 'event',
        Venue::class => 'venue',
        Canal::class => 'canal',
    ];

    /**
     * Podmienky pre daný typ, tak ako idú do prehliadača.
     *
     * @return array<int, array{key: string, rule: string, fields: array<int, string>, value?: int}>
     */
    public function rules(string $kind): array
    {
        return (array) config('content_review.readiness.'.$kind, []);
    }

    /** Podmienky pre všetky typy naraz — odpoveď endpointu pre formuláre. */
    public function allRules(): array
    {
        return (array) config('content_review.readiness', []);
    }

    /**
     * Vyhodnotenie nad plochým poľom hodnôt.
     *
     * Vstupom je zámerne pole, nie model: formulár posiela rozpísanú adresu
     * a ešte nenahratý obrázok, teda stav, ktorý v databáze neexistuje.
     *
     * @param  array<string, mixed>  $values
     * @return array{ready: bool, missing: array<int, string>, satisfied: int, total: int}
     */
    public function evaluate(string $kind, array $values): array
    {
        $missing = [];
        $rules = $this->rules($kind);

        foreach ($rules as $rule) {
            if (! $this->satisfies($rule, $values)) {
                $missing[] = $rule['key'];
            }
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
            'satisfied' => count($rules) - count($missing),
            'total' => count($rules),
        ];
    }

    /** To isté nad uloženým záznamom — `image` sa doplní z príloh. */
    public function evaluateModel(Model $model): array
    {
        $kind = self::ALIASES[$model::class] ?? null;

        if ($kind === null) {
            return ['ready' => false, 'missing' => [], 'satisfied' => 0, 'total' => 0];
        }

        return $this->evaluate($kind, $this->valuesFrom($model));
    }

    /** Alias typu, alebo null pre model, ktorý pripravenosť nerieši. */
    public function aliasFor(Model $model): ?string
    {
        return self::ALIASES[$model::class] ?? null;
    }

    /**
     * Ploché hodnoty záznamu.
     *
     * `municipality_id` je tu preto, že miesto má obec v stĺpci `village_id`
     * a kanál v `municipality_id` — formulár aj konfigurácia poznajú jedno
     * meno, rovnako ako `addressFrom()` na strane prehliadača.
     *
     * @return array<string, mixed>
     */
    private function valuesFrom(Model $model): array
    {
        return [
            'name' => $model->getAttribute('name'),
            'body' => $model->getAttribute('body'),
            'start_at' => $model->getAttribute('start_at'),
            'venue_id' => $model->getAttribute('venue_id'),
            'website' => $model->getAttribute('website'),
            'email' => $model->getAttribute('email'),
            'phone' => $model->getAttribute('phone'),
            'municipality_id' => $model->getAttribute('village_id') ?? $model->getAttribute('municipality_id'),
            'image' => $model->getAttribute('primary_image'),
        ];
    }

    /** @param  array<string, mixed>  $values */
    private function satisfies(array $rule, array $values): bool
    {
        $fields = $rule['fields'] ?? [];

        return match ($rule['rule'] ?? '') {
            'filled' => collect($fields)->every(fn (string $f) => filled($values[$f] ?? null)),
            'any_of' => collect($fields)->contains(fn (string $f) => filled($values[$f] ?? null)),
            'min_chars' => $this->textLength($values[$fields[0] ?? ''] ?? null) >= (int) ($rule['value'] ?? 0),
            default => true,
        };
    }

    /**
     * Dĺžka viditeľného textu. Popis je HTML, takže `strlen` by počítal značky
     * — prázdny odsek s odkazom by prešiel ako stostranový text.
     */
    public function textLength(mixed $value): int
    {
        if (! is_string($value)) {
            return 0;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strlen(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
    }
}
