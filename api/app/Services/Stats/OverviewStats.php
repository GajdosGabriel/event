<?php

namespace App\Services\Stats;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Enums\TicketPaymentStatus;
use App\Models\Admission;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Prehľadová štatistika pre úvodnú stránku dashboardu a adminu.
 *
 * Ten istý výpočet slúži obom — líši sa len rozsahom: dashboard vidí kanály,
 * ku ktorým má používateľ prístup, admin celý systém. Rozsah drží jediná
 * vlastnosť ({@see self::$canalIds}); `null` znamená „bez obmedzenia".
 *
 * Počty za obdobia sa nerátajú dotazom na obdobie, ale jedným dotazom na
 * tabuľku s podmienenou agregáciou — inak by prehľad s 10 metrikami × 4
 * obdobiami × 2 okná znamenal ~80 dotazov na jedno načítanie stránky.
 */
final class OverviewStats
{
    /** Dĺžka trendovej krivky v dňoch — zároveň dĺžka obdobia „mesiac". */
    public const TREND_DAYS = 30;

    /** Prefix aliasu pre predchádzajúce (porovnávacie) okno obdobia. */
    private const PREVIOUS = 'prev_';

    /** @var array<string, array{label: string, from: ?CarbonImmutable}> */
    private array $periodMeta;

    /** @var array<string, array{0: ?CarbonImmutable, 1: ?CarbonImmutable}> */
    private array $windows;

    /**
     * @param list<int>|null $canalIds  null = celý systém (admin)
     * @param int|null $recipientUserId komu chodia správy (dashboard); null = všetky
     */
    private function __construct(
        private readonly ?array $canalIds,
        private readonly ?int $recipientUserId,
        private readonly CarbonImmutable $now,
    ) {
        $this->periodMeta = [
            'day'   => ['label' => 'Dnes', 'from' => $this->now->startOfDay()],
            'week'  => ['label' => 'Posledných 7 dní', 'from' => $this->now->subDays(7)],
            'month' => ['label' => 'Posledných 30 dní', 'from' => $this->now->subDays(self::TREND_DAYS)],
            'all'   => ['label' => 'Celkovo', 'from' => null],
        ];

        $this->windows = [];

        foreach ($this->periodMeta as $key => $meta) {
            $from = $meta['from'];

            // Aktuálne okno je zhora otvorené. Keby končilo presne „teraz",
            // záznam vzniknutý v tej istej sekunde ako požiadavka by vypadol.
            $this->windows[$key] = [$from, null];

            if ($from === null) {
                continue;
            }

            // Porovnávame s rovnako dlhým oknom tesne pred aktuálnym. Pri „Dnes"
            // to znamená rovnaký úsek včerajška, nie celý včerajšok — inak by
            // ranné čísla vyzerali ako prepad oproti celému predošlému dňu.
            $length = (int) $from->diffInSeconds($this->now);
            $this->windows[self::PREVIOUS . $key] = [$from->subSeconds($length), $from];
        }
    }

    public static function forUser(User $user): self
    {
        return new self(
            $user->dashboardCanalIds()->all(),
            (int) $user->id,
            CarbonImmutable::now(),
        );
    }

    public static function forSystem(): self
    {
        return new self(null, null, CarbonImmutable::now());
    }

    public function build(): array
    {
        return [
            'scope' => $this->canalIds === null ? 'admin' : 'dashboard',
            'generated_at' => $this->now->toIso8601String(),
            'trend_days' => self::TREND_DAYS,
            'periods' => $this->periods(),
            'totals' => $this->totals(),
            'trend' => $this->trend(),
            'ticketing' => $this->ticketing(),
            'statuses' => $this->statusBreakdown(),
            'sources' => $this->sourceBreakdown(),
            'attention' => $this->attention(),
            'top_events' => $this->topEvents(),
            'upcoming' => $this->upcoming(),
            'top_canals' => $this->topCanals(),
            'users' => $this->users(),
        ];
    }

    // ---------------------------------------------------------------- rozsah

    private function eventQuery(): Builder
    {
        $query = Event::query();

        if ($this->canalIds !== null) {
            $query->whereIn('events.canal_id', $this->canalIds);
        }

        return $query;
    }

    /**
     * Poddotaz s id podujatí v rozsahu. Lístky ani vstupenky nemajú canal_id,
     * viažu sa cez podujatie — a poddotaz je lacnejší ako načítať tisíce id do PHP.
     */
    private function scopedEventIds(): ?QueryBuilder
    {
        if ($this->canalIds === null) {
            return null;
        }

        return DB::table('events')
            ->select('id')
            ->whereNull('deleted_at')
            ->whereIn('canal_id', $this->canalIds);
    }

    private function ticketQuery(): Builder
    {
        $query = Ticket::query();

        if ($ids = $this->scopedEventIds()) {
            $query->whereIn('tickets.event_id', $ids);
        }

        return $query;
    }

    private function admissionQuery(): Builder
    {
        $query = Admission::query();

        if ($ids = $this->scopedEventIds()) {
            $query->whereIn('ticket_admissions.event_id', $ids);
        }

        return $query;
    }

    private function ticketTypeQuery(): Builder
    {
        $query = TicketType::query();

        if ($ids = $this->scopedEventIds()) {
            $query->whereIn('ticket_types.event_id', $ids);
        }

        return $query;
    }

    private function venueQuery(): Builder
    {
        $query = Venue::query();

        if ($this->canalIds !== null) {
            $query->whereHas('canals', fn ($canal) => $canal->whereIn('canals.id', $this->canalIds));
        }

        return $query;
    }

    private function canalQuery(): Builder
    {
        $query = Canal::query();

        if ($this->canalIds !== null) {
            $query->whereIn('canals.id', $this->canalIds);
        }

        return $query;
    }

    private function messageQuery(): Builder
    {
        $query = Message::query();

        if ($this->recipientUserId !== null) {
            $query->where('messages.recipient_user_id', $this->recipientUserId);
        }

        return $query;
    }

    // ------------------------------------------------------------- agregácie

    /**
     * Spustí sadu podmienených agregácií jedným dotazom.
     *
     * @param array<string, array{0: string, 1: list<mixed>}> $expressions alias => [SQL, bindings]
     * @return array<string, int>
     */
    private function aggregate(Builder $query, array $expressions): array
    {
        $selects = [];
        $bindings = [];

        foreach ($expressions as $alias => [$sql, $args]) {
            $selects[] = "{$sql} as `{$alias}`";
            $bindings = [...$bindings, ...$args];
        }

        $row = (array) $query->toBase()
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        return array_map(static fn ($value) => (int) $value, $row);
    }

    /**
     * Počty (alebo súčty) rozdelené do všetkých období naraz — vrátane
     * porovnávacích okien. `$column` je stĺpec, ktorý určuje, kedy sa udalosť
     * stala; `$sumColumn` prepne počítanie na sčítavanie (napr. tržby).
     *
     * @return array<string, int>
     */
    private function buckets(Builder $query, string $column, ?string $sumColumn = null): array
    {
        $value = $sumColumn ?? '1';
        $expressions = [];

        foreach ($this->windows as $key => [$from, $to]) {
            $expressions[$key] = match (true) {
                // Obdobie „Celkovo" nemá hranice — ráta všetko, čo má stĺpec vyplnený.
                $from === null => ["SUM(CASE WHEN {$column} IS NOT NULL THEN {$value} ELSE 0 END)", []],
                // Aktuálne okno: zhora otvorené.
                $to === null => ["SUM(CASE WHEN {$column} >= ? THEN {$value} ELSE 0 END)", [$from]],
                // Porovnávacie okno: horná hranica je výlučná, nech sa nič neráta dvakrát.
                default => ["SUM(CASE WHEN {$column} >= ? AND {$column} < ? THEN {$value} ELSE 0 END)", [$from, $to]],
            };
        }

        return $this->aggregate($query, $expressions);
    }

    /**
     * Jedna metrika pre jedno obdobie: hodnota, porovnanie a percentuálna zmena.
     *
     * @param array<string, int> $buckets
     */
    private function metric(array $buckets, string $period): array
    {
        $value = $buckets[$period] ?? 0;
        $previous = $buckets[self::PREVIOUS . $period] ?? null;

        return [
            'value' => $value,
            'previous' => $previous,
            // Zmena voči nule je nekonečná — radšej ju nezobrazíme, než aby sme
            // z prvého lístka v histórii spravili „+100 %".
            'change' => ($previous === null || $previous === 0)
                ? null
                : round((($value - $previous) / $previous) * 100, 1),
        ];
    }

    // --------------------------------------------------------------- obdobia

    private function periods(): array
    {
        $definitions = [
            'events' => [
                'label' => 'Nové podujatia',
                'format' => 'number',
                'buckets' => $this->buckets($this->eventQuery(), 'events.created_at'),
            ],
            'events_published' => [
                'label' => 'Zverejnené podujatia',
                'format' => 'number',
                'buckets' => $this->buckets($this->eventQuery(), 'events.published_at'),
            ],
            'tickets' => [
                'label' => 'Objednávky / rezervácie',
                'format' => 'number',
                'buckets' => $this->buckets($this->ticketQuery(), 'tickets.created_at'),
            ],
            'admissions' => [
                'label' => 'Vydané vstupenky',
                'format' => 'number',
                'buckets' => $this->buckets($this->admissionQuery(), 'ticket_admissions.created_at'),
            ],
            'checkins' => [
                'label' => 'Príchody (check-in)',
                'format' => 'number',
                'buckets' => $this->buckets($this->admissionQuery(), 'ticket_admissions.checked_in_at'),
            ],
            'revenue' => [
                'label' => 'Zaplatené tržby',
                'format' => 'money',
                'buckets' => $this->buckets(
                    $this->ticketQuery()->where('tickets.payment_status', TicketPaymentStatus::Paid->value),
                    'tickets.created_at',
                    'tickets.price_amount',
                ),
            ],
            'venues' => [
                'label' => 'Nové miesta',
                'format' => 'number',
                'buckets' => $this->buckets($this->venueQuery(), 'venues.created_at'),
            ],
            'canals' => [
                'label' => 'Nové kanály',
                'format' => 'number',
                'buckets' => $this->buckets($this->canalQuery(), 'canals.created_at'),
            ],
            'messages' => [
                'label' => 'Prijaté správy',
                'format' => 'number',
                'buckets' => $this->buckets($this->messageQuery(), 'messages.created_at'),
            ],
        ];

        if ($this->canalIds === null) {
            $definitions['users'] = [
                'label' => 'Noví používatelia',
                'format' => 'number',
                'buckets' => $this->buckets(User::query(), 'users.created_at'),
            ];
        }

        $periods = [];

        foreach ($this->periodMeta as $key => $meta) {
            $metrics = [];

            foreach ($definitions as $metricKey => $definition) {
                $metrics[$metricKey] = [
                    'label' => $definition['label'],
                    'format' => $definition['format'],
                    ...$this->metric($definition['buckets'], $key),
                ];
            }

            $periods[$key] = [
                'label' => $meta['label'],
                'from' => $meta['from']?->toIso8601String(),
                'to' => $meta['from'] === null ? null : $this->now->toIso8601String(),
                'metrics' => $metrics,
            ];
        }

        return $periods;
    }

    // --------------------------------------------------------- aktuálny stav

    private function totals(): array
    {
        $now = $this->now;
        $today = $now->startOfDay();

        // „Prebieha alebo ešte len bude" — rovnaká definícia ako vo verejnom
        // výpise, aby sa počet v prehľade zhodoval s tým, čo návštevník vidí.
        $liveWindow = '(events.end_at >= ? OR (events.end_at IS NULL AND events.start_at >= ?))';

        $events = $this->aggregate($this->eventQuery(), [
            'total' => ['COUNT(*)', []],
            'published' => ['SUM(CASE WHEN events.status = ? THEN 1 ELSE 0 END)', [ModelStatus::Published->value]],
            'draft' => ['SUM(CASE WHEN events.status = ? THEN 1 ELSE 0 END)', [ModelStatus::Draft->value]],
            'archived' => ['SUM(CASE WHEN events.status = ? THEN 1 ELSE 0 END)', [ModelStatus::Archived->value]],
            'active' => [
                "SUM(CASE WHEN events.status = ? AND {$liveWindow} THEN 1 ELSE 0 END)",
                [ModelStatus::Published->value, $now, $today],
            ],
            'running' => [
                'SUM(CASE WHEN events.status = ? AND events.start_at <= ? AND (events.end_at IS NULL OR events.end_at >= ?) THEN 1 ELSE 0 END)',
                [ModelStatus::Published->value, $now, $now],
            ],
            'today' => [
                'SUM(CASE WHEN events.start_at >= ? AND events.start_at < ? THEN 1 ELSE 0 END)',
                [$today, $today->addDay()],
            ],
            'next_7d' => [
                'SUM(CASE WHEN events.start_at >= ? AND events.start_at < ? THEN 1 ELSE 0 END)',
                [$now, $now->addDays(7)],
            ],
            'with_ticketing' => [
                'SUM(CASE WHEN EXISTS (SELECT 1 FROM ticket_types tt WHERE tt.event_id = events.id AND tt.is_active = 1 AND tt.deleted_at IS NULL) THEN 1 ELSE 0 END)',
                [],
            ],
        ]);

        return [
            'events' => $events,
            'venues' => ['total' => $this->venueQuery()->count()],
            'canals' => ['total' => $this->canalQuery()->count()],
        ];
    }

    // ----------------------------------------------------------------- trend

    /** Denné počty za posledných {@see self::TREND_DAYS} dní, bez dier. */
    private function trend(): array
    {
        $from = $this->now->subDays(self::TREND_DAYS - 1)->startOfDay();

        $series = [
            'events' => $this->dailyCounts($this->eventQuery(), 'events.created_at', $from),
            'tickets' => $this->dailyCounts($this->ticketQuery(), 'tickets.created_at', $from),
            'admissions' => $this->dailyCounts($this->admissionQuery(), 'ticket_admissions.created_at', $from),
            'checkins' => $this->dailyCounts($this->admissionQuery(), 'ticket_admissions.checked_in_at', $from),
        ];

        $days = [];

        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $date = $from->addDays($offset)->toDateString();

            $days[] = [
                'date' => $date,
                'events' => $series['events'][$date] ?? 0,
                'tickets' => $series['tickets'][$date] ?? 0,
                'admissions' => $series['admissions'][$date] ?? 0,
                'checkins' => $series['checkins'][$date] ?? 0,
            ];
        }

        return $days;
    }

    /** @return array<string, int> dátum (Y-m-d) => počet */
    private function dailyCounts(Builder $query, string $column, CarbonImmutable $from): array
    {
        return $query->toBase()
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->where($column, '>=', $from)
            ->groupBy(DB::raw("DATE({$column})"))
            ->pluck('total', 'day')
            ->map(static fn ($value) => (int) $value)
            ->all();
    }

    // ------------------------------------------------------------- lístkovné

    private function ticketing(): array
    {
        $now = $this->now;

        $orders = $this->aggregate($this->ticketQuery(), [
            'total' => ['COUNT(*)', []],
            'paid' => ['SUM(CASE WHEN tickets.payment_status = ? THEN 1 ELSE 0 END)', [TicketPaymentStatus::Paid->value]],
            'awaiting_payment' => ['SUM(CASE WHEN tickets.payment_status = ? THEN 1 ELSE 0 END)', [TicketPaymentStatus::Pending->value]],
            'revenue_paid' => ['SUM(CASE WHEN tickets.payment_status = ? THEN tickets.price_amount ELSE 0 END)', [TicketPaymentStatus::Paid->value]],
            'revenue_awaiting' => ['SUM(CASE WHEN tickets.payment_status = ? THEN tickets.price_amount ELSE 0 END)', [TicketPaymentStatus::Pending->value]],
        ]);

        $seats = $this->aggregate($this->admissionQuery(), [
            'total' => ['COUNT(*)', []],
            'valid' => ['SUM(CASE WHEN ticket_admissions.status = ? THEN 1 ELSE 0 END)', [AdmissionStatus::Valid->value]],
            'cancelled' => ['SUM(CASE WHEN ticket_admissions.status = ? THEN 1 ELSE 0 END)', [AdmissionStatus::Cancelled->value]],
            'waitlisted' => ['SUM(CASE WHEN ticket_admissions.status = ? THEN 1 ELSE 0 END)', [AdmissionStatus::Waitlisted->value]],
            'awaiting_confirmation' => [
                'SUM(CASE WHEN ticket_admissions.confirmation_status = ? THEN 1 ELSE 0 END)',
                [AttendeeConfirmationStatus::Pending->value],
            ],
        ]);

        // Obsadenosť rátame len z typov, ktoré kapacitu naozaj majú — pri
        // neobmedzenom vstupe by podiel nemal menovateľa. Počet neobmedzených
        // typov vraciame zvlášť, nech je jasné, že podiel nepokrýva všetko.
        $capacity = $this->aggregate(
            $this->ticketTypeQuery()
                ->where('ticket_types.is_active', true)
                ->whereHas('event', fn ($event) => $event->where('events.start_at', '>=', $now)),
            [
                'seats' => ['SUM(CASE WHEN ticket_types.capacity IS NOT NULL THEN ticket_types.capacity ELSE 0 END)', []],
                'limited_types' => ['SUM(CASE WHEN ticket_types.capacity IS NOT NULL THEN 1 ELSE 0 END)', []],
                'unlimited_types' => ['SUM(CASE WHEN ticket_types.capacity IS NULL THEN 1 ELSE 0 END)', []],
            ],
        );

        $sold = $this->admissionQuery()
            ->where('ticket_admissions.status', AdmissionStatus::Valid->value)
            ->whereHas('ticketType', fn ($type) => $type->whereNotNull('ticket_types.capacity'))
            ->whereHas('event', fn ($event) => $event->where('events.start_at', '>=', $now))
            ->count();

        // Dochádzka sa dá hodnotiť až po podujatí — do miery príchodov preto
        // rátame len tie, ktoré sa už začali.
        $attendance = $this->aggregate(
            $this->admissionQuery()
                ->where('ticket_admissions.status', AdmissionStatus::Valid->value)
                ->whereHas('event', fn ($event) => $event->where('events.start_at', '<', $now)),
            [
                'expected' => ['COUNT(*)', []],
                'arrived' => ['SUM(CASE WHEN ticket_admissions.checked_in_at IS NOT NULL THEN 1 ELSE 0 END)', []],
            ],
        );

        return [
            'orders' => $orders,
            'seats' => $seats,
            'capacity' => [
                'seats' => $capacity['seats'],
                'sold' => $sold,
                'limited_types' => $capacity['limited_types'],
                'unlimited_types' => $capacity['unlimited_types'],
                'rate' => $this->rate($sold, $capacity['seats']),
            ],
            'attendance' => [
                'expected' => $attendance['expected'],
                'arrived' => $attendance['arrived'],
                'rate' => $this->rate($attendance['arrived'], $attendance['expected']),
            ],
        ];
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    // ------------------------------------------------------------- rozdelenia

    /** Podujatia podľa stavu — podiel rozpracovaného ku zverejnenému. */
    private function statusBreakdown(): array
    {
        $counts = $this->eventQuery()->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ModelStatus::cases())
            ->map(fn (ModelStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /** Vlastný obsah verzus importovaný — koľko z výpisu je naozaj „naše". */
    private function sourceBreakdown(): array
    {
        $counts = $this->aggregate($this->eventQuery(), [
            'own' => ["SUM(CASE WHEN events.orginal_source IS NULL OR events.orginal_source = '' THEN 1 ELSE 0 END)", []],
            'imported' => ["SUM(CASE WHEN events.orginal_source IS NOT NULL AND events.orginal_source <> '' THEN 1 ELSE 0 END)", []],
        ]);

        return [
            'own' => $counts['own'],
            'imported' => $counts['imported'],
            'imported_rate' => $this->rate($counts['imported'], $counts['own'] + $counts['imported']),
        ];
    }

    // -------------------------------------------------------- vyžaduje pozornosť

    /**
     * Zoznam vecí, ktoré má zmysel riešiť teraz. Zámerne nie je to isté ako
     * „chyby" — sú to stavy, ktoré sa samy nevyriešia a organizátorovi
     * inak uniknú (rozrobené koncepty, neuhradené objednávky, prázdne termíny).
     */
    private function attention(): array
    {
        $now = $this->now;
        $today = $now->startOfDay();

        $items = [];

        $staleDrafts = $this->eventQuery()
            ->where('events.status', ModelStatus::Draft->value)
            ->where('events.created_at', '<', $now->subDays(7))
            ->count();

        $items[] = [
            'key' => 'stale_drafts',
            'severity' => 'warning',
            'label' => 'Koncepty staršie ako týždeň',
            'hint' => 'Rozpracované podujatia, ktoré nikto nezverejnil.',
            'count' => $staleDrafts,
            'link' => 'events?status=draft',
        ];

        // Termín už prebehol, ale podujatie zostalo v koncepte — buď sa
        // nekonalo, alebo ho niekto zabudol zverejniť. Oboje treba doriešiť.
        $items[] = [
            'key' => 'past_drafts',
            'severity' => 'serious',
            'label' => 'Koncepty po termíne',
            'hint' => 'Podujatie sa malo konať, no nikdy nebolo zverejnené.',
            'count' => $this->eventQuery()
                ->where('events.status', ModelStatus::Draft->value)
                ->whereNotNull('events.start_at')
                ->where('events.start_at', '<', $now)
                ->count(),
            'link' => 'events?status=draft',
        ];

        $items[] = [
            'key' => 'missing_image',
            'severity' => 'warning',
            'label' => 'Zverejnené bez obrázka',
            'hint' => 'Vo výpise dostanú len zástupnú grafiku.',
            'count' => $this->eventQuery()
                ->where('events.status', ModelStatus::Published->value)
                ->where(fn ($query) => $query
                    ->whereNull('events.end_at')
                    ->where('events.start_at', '>=', $today)
                    ->orWhere('events.end_at', '>=', $now))
                ->whereDoesntHave('files', fn ($file) => $file
                    ->where('files.type', FileType::IMAGE->value)
                    ->where('files.is_primary', true))
                ->count(),
            'link' => 'events?status=published',
        ];

        // Termín sa blíži a nikto nie je prihlásený — ešte je čas propagovať.
        $items[] = [
            'key' => 'empty_upcoming',
            'severity' => 'warning',
            'label' => 'Blíži sa termín bez prihlásených',
            'hint' => 'Podujatia s registráciou do 7 dní, zatiaľ bez vstupeniek.',
            'count' => $this->eventQuery()
                ->where('events.status', ModelStatus::Published->value)
                ->whereBetween('events.start_at', [$now, $now->addDays(7)])
                ->whereHas('ticketTypes', fn ($type) => $type->where('ticket_types.is_active', true))
                ->whereDoesntHave('admissions', fn ($seat) => $seat->where('ticket_admissions.status', AdmissionStatus::Valid->value))
                ->count(),
            'link' => 'events',
        ];

        $items[] = [
            'key' => 'overdue_confirmations',
            'severity' => 'serious',
            'label' => 'Nepotvrdené účasti po lehote',
            'hint' => 'Miesta sú blokované, hoci lehota na potvrdenie uplynula.',
            'count' => $this->admissionQuery()
                ->where('ticket_admissions.confirmation_status', AttendeeConfirmationStatus::Pending->value)
                ->whereNotNull('ticket_admissions.confirmation_deadline_at')
                ->where('ticket_admissions.confirmation_deadline_at', '<', $now)
                ->count(),
            'link' => 'events',
        ];

        $items[] = [
            'key' => 'unpaid_orders',
            'severity' => 'critical',
            'label' => 'Neuhradené objednávky nad 3 dni',
            'hint' => 'Držia miesto, ale platba neprišla.',
            'count' => $this->ticketQuery()
                ->where('tickets.payment_status', TicketPaymentStatus::Pending->value)
                ->where('tickets.created_at', '<', $now->subDays(3))
                ->count(),
            'link' => 'events',
        ];

        // Kapacita nad 90 % je posledná chvíľa na jej navýšenie alebo na
        // otvorenie ďalšieho typu lístka.
        $items[] = [
            'key' => 'almost_sold_out',
            'severity' => 'warning',
            'label' => 'Takmer vypredané typy lístkov',
            'hint' => 'Obsadenosť nad 90 % pri nadchádzajúcich podujatiach.',
            'count' => $this->ticketTypeQuery()
                ->where('ticket_types.is_active', true)
                ->whereNotNull('ticket_types.capacity')
                ->whereHas('event', fn ($event) => $event->where('events.start_at', '>=', $now))
                // Predaj porovnávame s kapacitou korelovaným poddotazom, nie cez
                // HAVING nad aliasom z withCount — ten by pri ->count() zmizol
                // spolu s pôvodným SELECT-om a dotaz by spadol.
                ->whereRaw(
                    '(SELECT COUNT(*) FROM ticket_admissions ta'
                    . ' WHERE ta.ticket_type_id = ticket_types.id'
                    . ' AND ta.status = ? AND ta.deleted_at IS NULL) >= ticket_types.capacity * 0.9',
                    [AdmissionStatus::Valid->value],
                )
                ->count(),
            'link' => 'events',
        ];

        if ($this->recipientUserId !== null) {
            $items[] = [
                'key' => 'unread_messages',
                'severity' => 'info',
                'label' => 'Neprečítané správy',
                'hint' => 'Otázky od návštevníkov, na ktoré nikto neodpovedal.',
                'count' => $this->messageQuery()->whereNull('messages.read_at')->count(),
                'link' => null,
            ];
        }

        return array_values(array_filter($items, static fn (array $item) => $item['count'] > 0));
    }

    // ---------------------------------------------------------------- výbery

    /** Nadchádzajúce podujatia s najväčším záujmom. */
    private function topEvents(): array
    {
        return $this->eventQuery()
            ->select(['events.id', 'events.name', 'events.start_at', 'events.status'])
            ->where('events.start_at', '>=', $this->now)
            ->withCount([
                'admissions as seats' => fn ($seat) => $seat->where('ticket_admissions.status', AdmissionStatus::Valid->value),
            ])
            ->withSum(
                ['ticketTypes as capacity' => fn ($type) => $type->where('ticket_types.is_active', true)],
                'capacity',
            )
            ->having('seats', '>', 0)
            ->orderByDesc('seats')
            ->orderBy('events.start_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'start_at' => $event->start_at?->toIso8601String(),
                'status' => $event->status?->value,
                'seats' => (int) $event->seats,
                'capacity' => $event->capacity === null ? null : (int) $event->capacity,
                'rate' => $event->capacity ? $this->rate((int) $event->seats, (int) $event->capacity) : null,
            ])
            ->all();
    }

    /** Najbližší program — čo nás čaká ako prvé. */
    private function upcoming(): array
    {
        return $this->eventQuery()
            ->select(['events.id', 'events.name', 'events.start_at', 'events.end_at', 'events.status', 'events.venue_id'])
            ->with('venue:id,name')
            ->where('events.start_at', '>=', $this->now)
            ->withCount([
                'admissions as seats' => fn ($seat) => $seat->where('ticket_admissions.status', AdmissionStatus::Valid->value),
            ])
            ->orderBy('events.start_at')
            ->limit(6)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'start_at' => $event->start_at?->toIso8601String(),
                'end_at' => $event->end_at?->toIso8601String(),
                'status' => $event->status?->value,
                'venue' => $event->venue?->name,
                'seats' => (int) $event->seats,
            ])
            ->all();
    }

    /** Najaktívnejšie kanály — v dashboarde tie moje, v admine celý systém. */
    private function topCanals(): array
    {
        $from = $this->periodMeta['month']['from'];

        return $this->canalQuery()
            ->select(['canals.id', 'canals.name'])
            ->withCount([
                'events as events_total',
                'events as events_recent' => fn ($event) => $event->where('events.created_at', '>=', $from),
            ])
            ->orderByDesc('events_recent')
            ->orderByDesc('events_total')
            ->limit(5)
            ->get()
            ->map(fn (Canal $canal) => [
                'id' => $canal->id,
                'name' => $canal->name,
                'events_total' => (int) $canal->events_total,
                'events_recent' => (int) $canal->events_recent,
            ])
            ->all();
    }

    /** Stav používateľskej základne — len pre admin rozsah. */
    private function users(): ?array
    {
        if ($this->canalIds !== null) {
            return null;
        }

        return $this->aggregate(User::query(), [
            'total' => ['COUNT(*)', []],
            'verified' => ['SUM(CASE WHEN users.email_verified_at IS NOT NULL THEN 1 ELSE 0 END)', []],
            'blocked' => ['SUM(CASE WHEN users.blocked_at IS NOT NULL AND (users.blocked_until IS NULL OR users.blocked_until > ?) THEN 1 ELSE 0 END)', [$this->now]],
            'active_30d' => ['SUM(CASE WHEN users.last_activity >= ? THEN 1 ELSE 0 END)', [$this->periodMeta['month']['from']]],
        ]);
    }
}
