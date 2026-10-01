<?php

namespace App\Http\Controllers\Concerns;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtre nad výpisom súborov — spoločné pre admin aj dashboard. Obe obrazovky
 * sú v skutočnosti jedna komponenta s dvoma rozsahmi, takže sa im nesmú
 * rozísť ani pravidlá filtrovania.
 */
trait FiltersFileListing
{
    /**
     * Prepínače kôša chodia z query stringu ako `true`/`false` (axios) alebo
     * `1`/`0`; pravidlo `boolean` pozná len druhé. Pred validáciou sa preto
     * zjednotia na 1/0, inak by „Len zmazané“ skončilo na 422.
     */
    protected function normalizeFileListingFlags(Request $request): void
    {
        $normalized = [];

        foreach (['with_trashed', 'deleted'] as $key) {
            $value = $request->query($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                $normalized[$key] = in_array(strtolower($value), ['true', 'on', 'yes'], true) ? 1 : 0;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
            $request->query->add($normalized);
        }
    }

    /**
     * Pravidlá pre `validate()`. Rozsahové filtre (`fileable_type`,
     * `fileable_id`) si každý kontrolér pridáva sám — v dashboarde má typ inú
     * povinnosť než v admine.
     *
     * @return array<string, array<int, string>>
     */
    protected function fileListingRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'with_trashed' => ['sometimes', 'boolean'],
            // Len zmazané — druhá poloha toho istého prepínača ako `with_trashed`.
            'deleted' => ['sometimes', 'boolean'],
            'kind' => ['sometimes', 'string', 'in:'.implode(',', File::KINDS)],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,name,largest,smallest'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ];
    }

    /**
     * Kôš má vo filtri tri polohy — bez zmazaných (predvolene), spolu so
     * zmazanými a len zmazané. Držia to dva booleany, aby staršie odkazy
     * s `with_trashed` ďalej fungovali.
     */
    protected function applyFileTrashState(Builder $query, Request $request): void
    {
        if ($request->boolean('deleted')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }
    }

    /**
     * Hľadanie, druh súboru, dátum nahratia a zoradenie.
     *
     * `latest()` je predvolené poradie; `bySort` ho prepíše len pri inej voľbe.
     * Poradie volaní kopíruje `applyCommonFilters` — `bySort` musí bežať pred
     * `bySearch`, ktorý radí podľa relevancie a predošlé kľúče si necháva ako
     * sekundárne.
     */
    protected function applyFileListFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->byKind($request->input('kind'))
            ->byDateRange($request->input('date_from'), $request->input('date_to'))
            ->latest()
            ->bySort($request->input('sort'))
            ->bySearch($request->input('search'));
    }
}
