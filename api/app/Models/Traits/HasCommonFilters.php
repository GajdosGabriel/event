<?php

namespace App\Models\Traits;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasCommonFilters
{
    protected static array $commonFilterColumnCache = [];

    protected array $commonPrimarySearchColumns = ['name', 'title', 'email'];

    protected array $commonSecondarySearchColumns = ['body'];

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || ! $this->hasCommonFilterColumn('status')) {
            return $query;
        }

        return $status ? $query->where($this->qualifyColumn('status'), $status) : $query;
    }

    public function scopeByPublished(Builder $query, ?bool $published): Builder
    {
        $hasPublishedAt = $this->hasCommonFilterColumn('published_at');
        $hasStatus = $this->hasCommonFilterColumn('status');

        if ($published === null || (! $hasPublishedAt && ! $hasStatus)) {
            return $query;
        }

        if ($published) {
            if ($hasPublishedAt) {
                $query->whereNotNull($this->qualifyColumn('published_at'));
            }

            if ($hasStatus) {
                $query->where($this->qualifyColumn('status'), ModelStatus::Published->value);
            }

            return $query;
        }

        return $query->where(function (Builder $filterQuery) use ($hasPublishedAt, $hasStatus) {
            if ($hasPublishedAt) {
                $filterQuery->whereNull($this->qualifyColumn('published_at'));
            }

            if ($hasStatus) {
                $method = $hasPublishedAt ? 'orWhere' : 'where';
                $filterQuery->{$method}($this->qualifyColumn('status'), '!=', ModelStatus::Published->value);
            }
        });
    }

    public function scopeByBlocked(Builder $query, ?bool $blocked): Builder
    {
        if ($blocked === null) {
            return $query;
        }

        if ($this->hasCommonFilterColumn('status')) {
            return $blocked
                ? $query->where($this->qualifyColumn('status'), ModelStatus::Blocked->value)
                : $query->where($this->qualifyColumn('status'), '!=', ModelStatus::Blocked->value);
        }

        return $query;
    }

    public function scopeByDeleted(Builder $query, ?bool $deleted): Builder
    {
        if ($deleted === null || ! $this->usesSoftDeletes()) {
            return $query;
        }

        return $deleted ? $query->onlyTrashed() : $query->withoutTrashed();
    }

    public function scopeBySearch(Builder $query, ?string $search): Builder
    {
        $search = $this->normalizeSearchTerm($search);

        if ($search === null) {
            return $query;
        }

        $primaryColumns = $this->resolveCommonSearchColumns($this->commonPrimarySearchColumns);
        $secondaryColumns = $this->resolveCommonSearchColumns($this->commonSecondarySearchColumns);
        $allColumns = array_values(array_unique(array_merge($primaryColumns, $secondaryColumns)));

        if ($allColumns === []) {
            return $query;
        }

        $escapedSearch = $this->escapeLike($search);
        $likeTerm = "%{$escapedSearch}%";

        $query->where(function (Builder $searchQuery) use ($allColumns, $likeTerm) {
            foreach ($allColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $searchQuery->{$method}($this->qualifyColumn($column), 'like', $likeTerm);
            }
        });

        return $this->applySearchRelevanceOrdering($query, $primaryColumns, $secondaryColumns, $likeTerm);
    }

    public function scopeByMunicipality(Builder $query, ?int $municipality): Builder
    {
        if ($municipality === null) {
            return $query;
        }

        if ($this->hasCommonFilterColumn('village_id')) {
            return $query->where($this->qualifyColumn('village_id'), $municipality);
        }

        if (method_exists($this, 'venue')) {
            return $query->whereHas('venue', fn (Builder $q) => $q->where('village_id', $municipality));
        }

        return $query;
    }

    public function scopeByCanal(Builder $query, ?int $canalId): Builder
    {
        if ($canalId === null || ! $this->hasCommonFilterColumn('canal_id')) {
            return $query;
        }

        return $query->where($this->qualifyColumn('canal_id'), $canalId);
    }

    public function scopeByDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from === null && $to === null) {
            return $query;
        }

        $column = $this->hasCommonFilterColumn('start_at') ? 'start_at' : 'created_at';
        $qualified = $this->qualifyColumn($column);

        if ($from !== null) {
            $query->whereDate($qualified, '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate($qualified, '<=', $to);
        }

        return $query;
    }

    public function scopeBySort(Builder $query, ?string $sort): Builder
    {
        if ($sort === null || $sort === '' || $sort === 'newest') {
            return $query;
        }

        return match ($sort) {
            'oldest' => $query->reorder()
                ->orderBy($this->qualifyColumn('created_at'))
                ->orderBy($this->qualifyColumn('id')),
            'name' => $this->hasCommonFilterColumn('name')
                ? $query->reorder()->orderBy($this->qualifyColumn('name'))
                : $query,
            'upcoming' => $this->hasCommonFilterColumn('start_at')
                ? $this->applyUpcomingSort($query->reorder())
                : $query,
            default => $query,
        };
    }

    /**
     * "Najbližší termín": upcoming events first (soonest start_at on top), then
     * past events (most recently finished first), and finally events with no date.
     */
    protected function applyUpcomingSort(Builder $query): Builder
    {
        $column = $this->qualifyColumn('start_at');
        $now = now();

        return $query
            ->orderByRaw("{$column} IS NULL")
            ->orderByRaw("({$column} < ?)", [$now])
            ->orderByRaw("CASE WHEN {$column} >= ? THEN {$column} END ASC", [$now])
            ->orderByRaw("{$column} DESC");
    }

    public function scopeApplyCommonFilters(Builder $query, array $filters): Builder
    {
        // bySort must run before bySearch: search relevance ordering keeps
        // pre-existing orders as secondary sort keys.
        return $query
            ->byStatus($filters['status'] ?? null)
            ->byDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->bySort($filters['sort'] ?? null)
            ->bySearch($filters['search'] ?? null)
            ->byPublished($filters['published'] ?? null)
            ->byBlocked($filters['blocked'] ?? null)
            ->byDeleted($filters['deleted'] ?? null)
            ->byMunicipality($filters['municipality'] ?? null)
            ->byCanal($filters['canal_id'] ?? null);
    }

    protected function resolveCommonSearchColumns(array $columns): array
    {
        return array_values(array_filter($columns, fn (string $column) => $this->hasCommonFilterColumn($column)));
    }

    protected function normalizeSearchTerm(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $normalized = trim($search);

        return $normalized !== '' ? $normalized : null;
    }

    protected function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    protected function applySearchRelevanceOrdering(Builder $query, array $primaryColumns, array $secondaryColumns, string $likeTerm): Builder
    {
        $existingOrders = $query->getQuery()->orders ?? [];
        $caseSegments = [];
        $bindings = [];

        if ($primaryColumns !== []) {
            $caseSegments[] = 'WHEN ' . $this->buildLikeConditionSql($primaryColumns) . ' THEN 0';
            array_push($bindings, ...array_fill(0, count($primaryColumns), $likeTerm));
        }

        if ($secondaryColumns !== []) {
            $caseSegments[] = 'WHEN ' . $this->buildLikeConditionSql($secondaryColumns) . ' THEN 1';
            array_push($bindings, ...array_fill(0, count($secondaryColumns), $likeTerm));
        }

        if ($caseSegments === []) {
            return $query;
        }

        $query->reorder()->orderByRaw(
            'CASE ' . implode(' ', $caseSegments) . ' ELSE 2 END',
            $bindings
        );

        foreach ($existingOrders as $order) {
            if (isset($order['column'], $order['direction'])) {
                $query->orderBy($order['column'], $order['direction']);
            } elseif (($order['type'] ?? null) === 'Raw' && isset($order['sql'])) {
                $query->orderByRaw($order['sql']);
            }
        }

        return $query;
    }

    protected function buildLikeConditionSql(array $columns): string
    {
        return implode(' OR ', array_map(
            fn (string $column) => $this->qualifyColumn($column) . ' LIKE ?',
            $columns
        ));
    }

    protected function hasCommonFilterColumn(string $column): bool
    {
        $table = $this->getTable();
        $cacheKey = $table . ':' . $column;

        if (! array_key_exists($cacheKey, self::$commonFilterColumnCache)) {
            self::$commonFilterColumnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return self::$commonFilterColumnCache[$cacheKey];
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
    }
}
