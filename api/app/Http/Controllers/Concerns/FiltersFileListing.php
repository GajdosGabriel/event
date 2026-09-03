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
     * Pravidlá pre `validate()`. Rozsahové filtre (`fileable_type`,
     * `fileable_id`) si každý kontrolér pridáva sám — v dashboarde má typ inú
     * povinnosť než v admine.
     *
     * @return array<string, array<int, string>>
     */
    protected function fileListingRules(): array
    {
        return [
            'search'       => ['sometimes', 'string', 'max:100'],
            'with_trashed' => ['sometimes', 'boolean'],
            // Len zmazané — druhá poloha toho istého prepínača ako `with_trashed`.
            'deleted'      => ['sometimes', 'boolean'],
            'kind'         => ['sometimes', 'string', 'in:' . implode(',', File::KINDS)],
            'sort'         => ['sometimes', 'string', 'in:newest,oldest,name,largest,smallest'],
            'date_from'    => ['sometimes', 'date'],
            'date_to'      => ['sometimes', 'date'],
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
