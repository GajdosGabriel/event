<?php

namespace App\Models\Traits;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasCommonFilters
{
    /**
     * Slová kratšie než innodb_ft_min_token_size v FULLTEXT indexe vôbec nie sú,
     * takže by ich „+slovo*" nikdy nenašlo. 3 je default MySQL aj MariaDB.
     */
    protected const FULLTEXT_MIN_TOKEN_LENGTH = 3;

    protected static array $commonFilterColumnCache = [];

    protected static array $commonFulltextIndexCache = [];

    protected array $commonPrimarySearchColumns = ['name', 'title', 'email'];

    protected array $commonSecondarySearchColumns = ['body'];

    /**
     * Zapína FULLTEXT vyhľadávanie. Model to smie prepnúť na `true` len vtedy,
     * keď má indexy z migrácie `add_fulltext_search_indexes` — inak trait ticho
     * spadne späť na LIKE. Malé administratívne tabuľky (users, files,
     * organizations) ostávajú zámerne na LIKE: hľadá sa v nich podreťazec (kus
     * e-mailu, kus názvu súboru), čo FULLTEXT nerobí, a na ich objeme to nič
     * nestojí.
     *
     * Metóda, nie vlastnosť — PHP nedovolí modelu prekryť typovanú vlastnosť
     * z traitu inou predvolenou hodnotou.
     */
    protected function usesFulltextSearch(): bool
    {
        return false;
    }

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

    /**
     * Zmazané záznamy sú vo výpise len vtedy, keď si ich filter vyžiada.
     * Bez tohto by sa v zoznamoch miešali so živými (dashboard/admin dopyty
     * stavajú na `withTrashed()`, aby sa dali obnoviť) a nedalo by sa rozoznať,
     * čo je ešte platné.
     */
    public function scopeByDeleted(Builder $query, ?bool $deleted): Builder
    {
        if (! $this->usesSoftDeletes()) {
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

        $tokens = $this->fulltextTokens($search);

        if ($tokens !== [] && $this->supportsFulltextSearch()) {
            return $this->applyFulltextSearch($query, $primaryColumns, $allColumns, $tokens);
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

        // Kanál nesie obec priamo na sebe (`municipality_id`) — a práve podľa
        // nej ho počíta bočný prehľad obcí. Bez tejto vetvy by spadol až na
        // vetvu s miestami a filter by ukazoval iné záznamy, než na koľko sa
        // kliklo.
        if ($this->hasCommonFilterColumn('municipality_id')) {
            return $query->where($this->qualifyColumn('municipality_id'), $municipality);
        }

        if (method_exists($this, 'venue')) {
            return $query->whereHas('venue', fn (Builder $q) => $q->where('village_id', $municipality));
        }

        return $query;
    }

    /**
     * Filtrovanie podľa obsahových štítkov.
     *
     * Slugy, nie id — URL zostáva čitateľná (?tags=koncert,folklor) a prežije
     * preseedovanie číselníka. Viac štítkov znamená AND: „koncert AND folklór"
     * je zúženie, tak to od filtra čaká používateľ.
     *
     * @param  array<int, string>|null  $slugs
     */
    public function scopeByTags(Builder $query, ?array $slugs): Builder
    {
        if ($slugs === null || ! method_exists($this, 'tags')) {
            return $query;
        }

        $slugs = array_values(array_filter(array_unique(array_map(
            static fn ($slug) => trim((string) $slug),
            $slugs,
        ))));

        if ($slugs === []) {
            return $query;
        }

        foreach ($slugs as $slug) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('tags.slug', $slug));
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

    /**
     * Časové okno podujatia.
     *
     * Definície sú zámerne rovnaké ako dlaždice v prehľade
     * ({@see \App\Services\Stats\OverviewStats}) — po kliknutí na číslo má
     * výpis ukázať presne tie záznamy, ktoré sa doň rátali. Filter je čisto
     * časový; stav („zverejnené“) si pridáva volajúci cez `status`, inak by sa
     * nedal nájsť napríklad koncept s dnešným termínom.
     */
    public function scopeByPhase(Builder $query, ?string $phase): Builder
    {
        if ($phase === null || $phase === '' || ! $this->hasCommonFilterColumn('start_at')) {
            return $query;
        }

        $start = $this->qualifyColumn('start_at');
        $end = $this->hasCommonFilterColumn('end_at') ? $this->qualifyColumn('end_at') : null;
        $now = now();
        $today = $now->copy()->startOfDay();

        return match ($phase) {
            // „Ešte neskončilo“: koniec je v budúcnosti, alebo koniec vyplnený
            // nie je a začiatok padá najskôr na dnešok.
            'active' => $end === null
                ? $query->where($start, '>=', $today)
                : $query->where(fn (Builder $window) => $window
                    ->where($end, '>=', $now)
                    ->orWhere(fn (Builder $openEnded) => $openEnded
                        ->whereNull($end)
                        ->where($start, '>=', $today))),
            'running' => $end === null
                ? $query->where($start, '<=', $now)
                : $query
                    ->where($start, '<=', $now)
                    ->where(fn (Builder $window) => $window->whereNull($end)->orWhere($end, '>=', $now)),
            'today' => $query
                ->where($start, '>=', $today)
                ->where($start, '<', $today->copy()->addDay()),
            'next7d' => $query
                ->where($start, '>=', $now)
                ->where($start, '<', $now->copy()->addDays(7)),
            'past' => $end === null
                ? $query->where($start, '<', $now)
                : $query->where(fn (Builder $window) => $window
                    ->where($end, '<', $now)
                    ->orWhere(fn (Builder $openEnded) => $openEnded
                        ->whereNull($end)
                        ->where($start, '<', $today))),
            default => $query,
        };
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
            // Podľa veľkosti — dáva zmysel len tam, kde sa niečo ukladá (súbory);
            // ostatným modelom stĺpec chýba a poradie ostane nezmenené.
            'largest' => $this->hasCommonFilterColumn('size')
                ? $query->reorder()->orderByDesc($this->qualifyColumn('size'))
                : $query,
            'smallest' => $this->hasCommonFilterColumn('size')
                ? $query->reorder()->orderBy($this->qualifyColumn('size'))
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
            ->byPhase($filters['phase'] ?? null)
            ->bySort($filters['sort'] ?? null)
            ->bySearch($filters['search'] ?? null)
            ->byPublished($filters['published'] ?? null)
            ->byBlocked($filters['blocked'] ?? null)
            ->byDeleted($filters['deleted'] ?? null)
            ->byMunicipality($filters['municipality'] ?? null)
            ->byTags($filters['tags'] ?? null)
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

        return $this->restoreExistingOrders($query, $existingOrders);
    }

    /**
     * Zoradenie podľa relevancie prepisuje poradie z `bySort()`, ktoré beží skôr —
     * pôvodné kľúče sa preto vracajú späť ako sekundárne.
     */
    protected function restoreExistingOrders(Builder $query, array $existingOrders): Builder
    {
        foreach ($existingOrders as $order) {
            if (isset($order['column'], $order['direction'])) {
                $query->orderBy($order['column'], $order['direction']);
            } elseif (($order['type'] ?? null) === 'Raw' && isset($order['sql'])) {
                $query->orderByRaw($order['sql']);
            }
        }

        return $query;
    }

    /**
     * Rozpad hľadaného výrazu na tokeny tak, ako ho tokenizuje samotný index:
     * všetko okrem písmen a číslic je oddeľovač. Zároveň tým z výrazu vypadnú
     * operátory boolean módu (+ - * " ~ < > ( )), takže sa doň nedá nič prepašovať.
     *
     * Príliš krátke slová sa zahodia, nie odmietnu — „koncert v Košiciach"
     * má hľadať koncert + Košiciach, nie skončiť s prázdnym výsledkom.
     *
     * @return array<int, string>
     */
    protected function fulltextTokens(string $search): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $token) => mb_strlen($token) >= self::FULLTEXT_MIN_TOKEN_LENGTH
        ));
    }

    protected function supportsFulltextSearch(): bool
    {
        if (! $this->usesFulltextSearch()) {
            return false;
        }

        if (! in_array($this->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        // InnoDB dopĺňa FULLTEXT index až pri commite, takže v otvorenej
        // transakcii by MATCH nevidel riadky zapísané v tej istej transakcii
        // a vyhľadávanie by ticho vracalo menej výsledkov. LIKE takú dieru
        // nemá, tak sa v transakcii použije on. Verejné výpisy v transakcii
        // nebežia — reálne sa to týka testov a kódu, ktorý zapíše a hneď hľadá.
        if ($this->getConnection()->transactionLevel() > 0) {
            return false;
        }

        return $this->hasCommonFulltextIndex($this->getTable() . '_search_primary_fulltext')
            && $this->hasCommonFulltextIndex($this->getTable() . '_search_fulltext');
    }

    /**
     * @param  array<int, string>  $primaryColumns
     * @param  array<int, string>  $allColumns
     * @param  array<int, string>  $tokens
     */
    protected function applyFulltextSearch(Builder $query, array $primaryColumns, array $allColumns, array $tokens): Builder
    {
        // Každý token je povinný (`+`) a hľadá sa aj ako predpona (`*`), takže
        // „konc" nájde „koncert" a viacslovný dopyt funguje ako AND.
        $expression = implode(' ', array_map(fn (string $token) => '+' . $token . '*', $tokens));

        $query->whereRaw($this->matchSql($allColumns) . ' AGAINST (? IN BOOLEAN MODE)', [$expression]);

        $existingOrders = $query->getQuery()->orders ?? [];

        $query->reorder()->orderByRaw(
            'CASE WHEN ' . $this->matchSql($primaryColumns) . ' AGAINST (? IN BOOLEAN MODE) THEN 0 ELSE 1 END',
            [$expression]
        );

        return $this->restoreExistingOrders($query, $existingOrders);
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function matchSql(array $columns): string
    {
        return 'MATCH (' . implode(', ', array_map(
            fn (string $column) => $this->qualifyColumn($column),
            $columns
        )) . ')';
    }

    protected function hasCommonFulltextIndex(string $index): bool
    {
        $table = $this->getTable();
        $cacheKey = $table . ':' . $index;

        if (! array_key_exists($cacheKey, self::$commonFulltextIndexCache)) {
            $names = array_column($this->getConnection()->getSchemaBuilder()->getIndexes($table), 'name');
            self::$commonFulltextIndexCache[$cacheKey] = in_array($index, $names, true);
        }

        return self::$commonFulltextIndexCache[$cacheKey];
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
