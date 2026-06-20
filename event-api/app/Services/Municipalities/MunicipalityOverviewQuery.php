<?php

namespace App\Services\Municipalities;

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

        return $query
            ->reorder()
            ->join('municipalities', 'municipalities.id', '=', $municipalityColumn)
            ->whereNotNull($municipalityColumn)
            ->selectRaw("{$municipalityColumn} as municipality_id, municipalities.fullname as municipality_name, municipalities.shortname as municipality_shortname, {$countExpression} as events_count")
            ->groupBy($municipalityColumn, 'municipalities.fullname', 'municipalities.shortname')
            ->orderByDesc('events_count')
            ->orderBy('municipalities.fullname');
    }
}
