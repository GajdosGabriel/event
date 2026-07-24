<?php

namespace App\Services\Municipalities;

use App\Support\SlovakRegions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class MunicipalityOverviewQuery
{
    public function apply(
        Builder|Relation $query,
        string $municipalityColumn,
        string $countColumn,
        bool $distinctCount = false,
    ): Builder {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        $countExpression = $distinctCount
            ? "COUNT(DISTINCT {$countColumn})"
            : "COUNT({$countColumn})";

        // Kraj (VÚC) beriem priamo z dotazu, aby si ho front vedel zoskupiť bez
        // ďalšieho volania. Názov skladá CASE, lebo číselník krajov nemá tabuľku.
        $regionNameExpression = SlovakRegions::caseExpression('municipalities.region_id');

        return $query
            ->reorder()
            ->join('municipalities', 'municipalities.id', '=', $municipalityColumn)
            ->whereNotNull($municipalityColumn)
            ->selectRaw("{$municipalityColumn} as municipality_id, municipalities.fullname as municipality_name, municipalities.shortname as municipality_shortname, municipalities.region_id as region_id, {$regionNameExpression} as region_name, {$countExpression} as events_count")
            ->groupBy($municipalityColumn, 'municipalities.fullname', 'municipalities.shortname', 'municipalities.region_id')
            ->orderByDesc('events_count')
            ->orderBy('municipalities.fullname');
    }
}
