<?php

namespace App\Services\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\QuestionType;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventQuestionOption;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Dotazník k registrácii — jediné miesto, ktoré rozhoduje, aké otázky platia,
 * či je odpoveď v poriadku, koľko stojí a či na ňu ešte ostala kapacita.
 *
 * Odpovede prichádzajú ako mapa `{question_id: hodnota}`, kde hodnota je
 * text / číslo / dátum, bool (checkbox), id možnosti alebo pole id možností.
 *
 * Volá sa v transakcii volajúceho — kapacitu možností drží `lockForUpdate`.
 */
class Questionnaire
{
    /** Relácie objednávky potrebné na zobrazenie odpovedí (present()). */
    public const TICKET_RELATIONS = [
        'answers.question',
        'answers.option',
        'admissions.answers.question',
        'admissions.answers.option',
    ];

    private const TEXT_MAX = 500;

    private const TEXTAREA_MAX = 2000;

    /** @var array<int, int> Počty v tejto požiadavke podľa option_id (kapacita). */
    private array $pending = [];

    /**
     * Aktívne otázky podujatia s aktívnymi možnosťami.
     *
     * @return Collection<int, EventQuestion>
     */
    public function activeQuestions(Event $event): Collection
    {
        return $event->questions()
            ->where('is_active', true)
            ->with(['options' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }

    /** Má podujatie povinnú otázku? Potom nejde rezervovať na jedno kliknutie. */
    public function hasRequiredQuestions(Event $event): bool
    {
        return $event->questions()->where('is_active', true)->where('is_required', true)->exists();
    }

    /**
     * Overí odpovede novej objednávky a pripraví riadky na zápis.
     * Volá sa pred vytvorením objednávky — cena možností ide do jej ceny.
     *
     * @param  array<int|string, mixed>  $orderAnswers
     * @param  array<int, array{type: TicketType, answers: array<int|string, mixed>, key: string}>  $seats
     * @return array{order: array<int, array<string, mixed>>, seats: array<int, array<int, array<string, mixed>>>, price: int}
     */
    public function prepare(Event $event, array $orderAnswers, array $seats): array
    {
        $this->pending = [];
        $questions = $this->activeQuestions($event);
        $errors = [];

        $order = $this->resolveSet(
            $questions->filter(fn (EventQuestion $q) => $q->isOrderScope()),
            $orderAnswers,
            'answers',
            $errors,
            enforceRequired: true,
        );

        $seatRows = [];
        foreach ($seats as $i => $seat) {
            $seatRows[$i] = $this->resolveSet(
                $questions->filter(fn (EventQuestion $q) => ! $q->isOrderScope() && $q->appliesToType($seat['type'])),
                $seat['answers'],
                $seat['key'],
                $errors,
                enforceRequired: true,
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->checkCapacity([], $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $price = $this->priceOf($order);
        foreach ($seatRows as $rows) {
            $price += $this->priceOf($rows);
        }

        return ['order' => $order, 'seats' => $seatRows, 'price' => $price];
    }

    /**
     * Zapíše pripravené odpovede k vytvorenej objednávke.
     *
     * @param  array{order: array<int, array<string, mixed>>, seats: array<int, array<int, array<string, mixed>>>, price: int}  $prepared
     * @param  array<int, Admission>  $admissions  V poradí, v akom išli miesta do prepare().
     */
    public function store(Ticket $ticket, array $prepared, array $admissions): void
    {
        foreach ($prepared['order'] as $row) {
            $ticket->answers()->create($row);
        }

        foreach ($prepared['seats'] as $i => $rows) {
            $admission = $admissions[$i] ?? null;
            if ($admission === null) {
                continue;
            }

            foreach ($rows as $row) {
                $ticket->answers()->create($row + ['admission_id' => $admission->id]);
            }
        }
    }

    /**
     * Úprava odpovedí existujúcej objednávky (účastník alebo organizátor).
     * Mení len otázky, ktoré sú v požiadavke; ostatné ostávajú.
     *
     * @param  array<int|string, mixed>|null  $orderAnswers  null = odpovede za objednávku sa nemenia.
     * @param  array<int, array<int|string, mixed>>  $seatAnswers  admission_id => odpovede.
     */
    public function update(Ticket $ticket, ?array $orderAnswers, array $seatAnswers, bool $asOrganizer): Ticket
    {
        $this->pending = [];
        $ticket->loadMissing(['event', 'admissions.ticketType', 'answers']);
        $questions = $this->editableQuestions($ticket->event, $asOrganizer);
        $errors = [];
        $changes = []; // [admission_id|null, question_id, rows[]]

        if ($orderAnswers !== null) {
            $set = $questions->filter(fn (EventQuestion $q) => $q->isOrderScope());
            $this->collectChanges($ticket, null, $set, $orderAnswers, 'answers', $asOrganizer, $errors, $changes);
        }

        foreach ($seatAnswers as $admissionId => $answers) {
            /** @var Admission|null $admission */
            $admission = $ticket->admissions->firstWhere('id', (int) $admissionId);
            if ($admission === null || ! is_array($answers)) {
                continue;
            }

            $set = $questions->filter(fn (EventQuestion $q) => ! $q->isOrderScope() && $q->appliesToType($admission->ticketType));
            $this->collectChanges($ticket, $admission, $set, $answers, 'admissions.'.$admission->id, $asOrganizer, $errors, $changes);
        }

        if ($errors === []) {
            $this->checkCapacity($this->replacedOptionCounts($ticket, $changes), $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $delta = 0;
        foreach ($changes as [$admissionId, $questionId, $rows]) {
            $existing = $this->existingRows($ticket, $admissionId, $questionId);
            $delta -= (int) $existing->sum('price_amount');
            TicketAnswer::query()->whereKey($existing->pluck('id'))->delete();

            foreach ($rows as $row) {
                $ticket->answers()->create($row + ['admission_id' => $admissionId]);
            }
            $delta += $this->priceOf($rows);
        }

        if ($delta !== 0) {
            $this->applyPriceDelta($ticket, $delta);
        }

        return $ticket->fresh(['answers', 'admissions']);
    }

    /**
     * Odpovede zoskupené po otázkach — pre API, export aj e-mail.
     * `display` je čitateľný text („Vegetariánska, Bezlepková“), `value`
     * a `option_ids` slúžia na predvyplnenie formulára pri úprave.
     *
     * @param  iterable<TicketAnswer>  $answers  S načítanými `question` a `option`.
     * @return array<int, array{question_id: int, label: string, value: ?string, option_ids: array<int, int>, display: string, price_amount: int}>
     */
    public function present(iterable $answers): array
    {
        $grouped = [];

        foreach ($answers as $answer) {
            $id = (int) $answer->question_id;
            $grouped[$id] ??= [
                'question_id' => $id,
                'label' => (string) $answer->question?->label,
                'sort' => [(int) $answer->question?->sort_order, $id],
                'value' => null,
                'option_ids' => [],
                'parts' => [],
                'price_amount' => 0,
            ];

            if ($answer->option_id !== null) {
                $grouped[$id]['option_ids'][] = (int) $answer->option_id;
                $grouped[$id]['parts'][] = (string) $answer->option?->label;
            } elseif ($answer->question?->type === QuestionType::Checkbox) {
                $grouped[$id]['value'] = '1';
                $grouped[$id]['parts'][] = __('tickets.answer_yes');
            } else {
                $grouped[$id]['value'] = $answer->value;
                $grouped[$id]['parts'][] = (string) $answer->value;
            }

            $grouped[$id]['price_amount'] += (int) $answer->price_amount;
        }

        usort($grouped, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($row) => [
            'question_id' => $row['question_id'],
            'label' => $row['label'],
            'value' => $row['value'],
            'option_ids' => $row['option_ids'],
            'display' => implode(', ', $row['parts']),
            'price_amount' => $row['price_amount'],
        ], $grouped);
    }

    /**
     * Súčty odpovedí pre organizátora: pri výberových otázkach počet a tržba
     * po možnostiach, pri ostatných počet vyplnených. Rátajú sa len odpovede
     * nezrušených objednávok a platných miest (rovnako ako kapacita).
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(Event $event): array
    {
        $questions = $event->questions()->with('options')->get();

        $rows = TicketAnswer::query()
            ->whereIn('question_id', $questions->pluck('id'))
            ->whereHas('ticket', fn ($q) => $q->where('status', '!=', TicketStatus::Cancelled->value))
            ->where(fn ($q) => $q
                ->whereNull('admission_id')
                ->orWhereHas('admission', fn ($a) => $a->where('status', AdmissionStatus::Valid->value)))
            ->selectRaw('question_id, option_id, COUNT(*) as aggregate, COALESCE(SUM(price_amount), 0) as revenue')
            ->groupBy('question_id', 'option_id')
            ->get();

        return $questions->map(function (EventQuestion $question) use ($rows) {
            $own = $rows->where('question_id', $question->id);

            return [
                'id' => $question->id,
                'label' => $question->label,
                'type' => $question->type->value,
                'scope' => $question->scope->value,
                'is_active' => $question->is_active,
                'answered_count' => $question->type->hasOptions() ? null : (int) $own->sum('aggregate'),
                'options' => $question->type->hasOptions()
                    ? $question->options->map(function (EventQuestionOption $option) use ($own) {
                        $row = $own->firstWhere('option_id', $option->id);

                        return [
                            'id' => $option->id,
                            'label' => $option->label,
                            'count' => (int) ($row->aggregate ?? 0),
                            'capacity' => $option->capacity,
                            'revenue' => (int) ($row->revenue ?? 0),
                            'is_active' => $option->is_active,
                        ];
                    })->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    /**
     * Do kedy smie účastník meniť odpovede: termín registrácie, inak začiatok
     * podujatia. Null = bez obmedzenia (podujatie bez dátumu).
     */
    public function editableUntil(Event $event): ?Carbon
    {
        return $event->registration_deadline_at ?? $event->start_at;
    }

    public function attendeeCanEdit(Ticket $ticket): bool
    {
        $until = $this->editableUntil($ticket->event);

        return $until === null || $until->isFuture();
    }

    /**
     * Otázky, ktoré sa dajú meniť. Organizátor mení aj neaktívne (aby opravil
     * staré odpovede), účastník len aktívne a editovateľné.
     *
     * @return Collection<int, EventQuestion>
     */
    private function editableQuestions(Event $event, bool $asOrganizer): Collection
    {
        $query = $event->questions()->with('options');

        if (! $asOrganizer) {
            $query->where('is_active', true)->where('editable_by_attendee', true);
        }

        return $query->get();
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, array{0: ?int, 1: int, 2: array<int, array<string, mixed>>}>  $changes
     */
    private function collectChanges(
        Ticket $ticket,
        ?Admission $admission,
        Collection $questions,
        array $answers,
        string $keyPrefix,
        bool $asOrganizer,
        array &$errors,
        array &$changes,
    ): void {
        foreach ($questions as $question) {
            if (! array_key_exists($question->id, $answers) && ! array_key_exists((string) $question->id, $answers)) {
                continue;
            }

            $existing = $this->existingRows($ticket, $admission?->id, $question->id);
            $key = $keyPrefix.'.'.$question->id;
            $rows = $this->resolveOne(
                $question,
                $answers[$question->id] ?? $answers[(string) $question->id] ?? null,
                $key,
                $errors,
                enforceRequired: ! $asOrganizer,
                allowedOptionIds: $existing->pluck('option_id')->filter()->all(),
            );

            if ($rows === null) {
                continue;
            }

            // Zaplatenú možnosť s cenou mení len organizátor — inak by sa
            // účastník mohol dodatočne vzdať zaplateného alebo pridať nezaplatené.
            if (! $asOrganizer
                && $ticket->payment_status === TicketPaymentStatus::Paid
                && $this->pricedOptionIds($existing->all()) !== $this->pricedOptionIds($rows)) {
                $errors[$key][] = __('tickets.errors.answer_paid_locked');

                continue;
            }

            $changes[] = [$admission?->id, $question->id, $rows];
        }
    }

    /**
     * @param  Collection<int, EventQuestion>  $questions
     * @param  array<int|string, mixed>  $answers
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function resolveSet(Collection $questions, array $answers, string $keyPrefix, array &$errors, bool $enforceRequired): array
    {
        $rows = [];

        foreach ($questions as $question) {
            $value = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            $resolved = $this->resolveOne($question, $value, $keyPrefix.'.'.$question->id, $errors, $enforceRequired);

            if ($resolved !== null) {
                array_push($rows, ...$resolved);
            }
        }

        return $rows;
    }

    /**
     * Prevedie jednu hodnotu na riadky odpovede. Null = chyba (zapísaná do $errors).
     *
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, int>  $allowedOptionIds  Už zvolené možnosti, ktoré medzičasom
     *                                             organizátor vypol — tie sa ponechať smú.
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveOne(
        EventQuestion $question,
        mixed $value,
        string $key,
        array &$errors,
        bool $enforceRequired,
        array $allowedOptionIds = [],
    ): ?array {
        $base = ['question_id' => $question->id];

        if ($question->type->hasOptions()) {
            $ids = collect(is_array($value) ? $value : [$value])
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($question->type === QuestionType::SingleChoice && $ids->count() > 1) {
                $errors[$key][] = __('tickets.errors.answer_invalid');

                return null;
            }

            if ($ids->isEmpty()) {
                return $this->emptyOrRequired($question, $key, $errors, $enforceRequired);
            }

            $options = $question->relationLoaded('options') ? $question->options : $question->options()->get();
            $rows = [];

            foreach ($ids as $id) {
                /** @var EventQuestionOption|null $option */
                $option = $options->firstWhere('id', $id);

                $usable = $option !== null && ($option->is_active || in_array($id, $allowedOptionIds, true));
                if (! $usable) {
                    $errors[$key][] = __('tickets.errors.answer_invalid');

                    return null;
                }

                if ($option->capacity !== null && ! in_array($id, $allowedOptionIds, true)) {
                    $this->pending[$id] = ($this->pending[$id] ?? 0) + 1;
                }

                $rows[] = $base + [
                    'option_id' => $option->id,
                    'value' => null,
                    'price_amount' => $option->price_amount ?: null,
                ];
            }

            return $rows;
        }

        if ($question->type === QuestionType::Checkbox) {
            $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

            return $checked
                ? [$base + ['option_id' => null, 'value' => '1', 'price_amount' => null]]
                : $this->emptyOrRequired($question, $key, $errors, $enforceRequired);
        }

        $text = is_scalar($value) ? trim((string) $value) : '';

        if ($text === '') {
            return $this->emptyOrRequired($question, $key, $errors, $enforceRequired);
        }

        $valid = match ($question->type) {
            QuestionType::Number => is_numeric($text),
            QuestionType::Date => $this->isDate($text),
            QuestionType::Textarea => mb_strlen($text) <= self::TEXTAREA_MAX,
            default => mb_strlen($text) <= self::TEXT_MAX,
        };

        if (! $valid) {
            $errors[$key][] = __('tickets.errors.answer_invalid');

            return null;
        }

        return [$base + ['option_id' => null, 'value' => $text, 'price_amount' => null]];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, array<string, mixed>>|null
     */
    private function emptyOrRequired(EventQuestion $question, string $key, array &$errors, bool $enforceRequired): ?array
    {
        if ($enforceRequired && $question->is_required) {
            $errors[$key][] = __('tickets.errors.answer_required', ['label' => $question->label]);

            return null;
        }

        return [];
    }

    private function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * Overí kapacitu možností zvolených v tejto požiadavke. `$released` sú
     * miesta, ktoré úprava uvoľní (staré odpovede tej istej objednávky).
     *
     * @param  array<int, int>  $released
     * @param  array<string, array<int, string>>  $errors
     */
    private function checkCapacity(array $released, array &$errors): void
    {
        if ($this->pending === []) {
            return;
        }

        $options = EventQuestionOption::query()
            ->whereKey(array_keys($this->pending))
            ->lockForUpdate()
            ->get();

        foreach ($options as $option) {
            $taken = $option->takenCount() - ($released[$option->id] ?? 0);
            $remaining = max(0, $option->capacity - $taken);

            if ($this->pending[$option->id] > $remaining) {
                $errors['answers'][] = trans_choice('tickets.counts.option_remaining', $remaining, ['name' => $option->label]);
            }
        }
    }

    /**
     * Koľko obsadených miest na možnosť uvoľnia nahrádzané odpovede.
     *
     * @param  array<int, array{0: ?int, 1: int, 2: array<int, array<string, mixed>>}>  $changes
     * @return array<int, int>
     */
    private function replacedOptionCounts(Ticket $ticket, array $changes): array
    {
        $released = [];

        foreach ($changes as [$admissionId, $questionId]) {
            foreach ($this->existingRows($ticket, $admissionId, $questionId) as $row) {
                if ($row->option_id !== null) {
                    $released[$row->option_id] = ($released[$row->option_id] ?? 0) + 1;
                }
            }
        }

        return $released;
    }

    /** @return Collection<int, TicketAnswer> */
    private function existingRows(Ticket $ticket, ?int $admissionId, int $questionId): Collection
    {
        return $ticket->answers
            ->where('question_id', $questionId)
            ->filter(fn (TicketAnswer $a) => ($a->admission_id === null ? null : (int) $a->admission_id) === $admissionId)
            ->values();
    }

    /** @param  array<int, array<string, mixed>|TicketAnswer>  $rows */
    private function priceOf(array $rows): int
    {
        return (int) array_sum(array_map(fn ($row) => (int) ($row['price_amount'] ?? 0), $rows));
    }

    /**
     * @param  array<int, array<string, mixed>|TicketAnswer>  $rows
     * @return array<int, int>
     */
    private function pricedOptionIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if ((int) ($row['price_amount'] ?? 0) > 0) {
                $ids[] = (int) $row['option_id'];
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * Zmena odpovedí s cenou posunie cenu objednávky. Doplatok vráti
     * objednávku do „čaká na platbu"; nulová cena platbu zruší.
     */
    private function applyPriceDelta(Ticket $ticket, int $delta): void
    {
        $price = max(0, (int) $ticket->price_amount + $delta);
        $status = $ticket->payment_status;

        if ($price === 0 && $status === TicketPaymentStatus::Pending) {
            $status = TicketPaymentStatus::None;
        } elseif ($price > 0 && ($status === TicketPaymentStatus::None || ($delta > 0 && $status === TicketPaymentStatus::Paid))) {
            $status = TicketPaymentStatus::Pending;
        }

        $ticket->update([
            'price_amount' => $price > 0 ? $price : null,
            'price_currency' => $price > 0 ? ($ticket->price_currency ?? 'EUR') : null,
            'payment_status' => $status->value,
        ]);
    }
}
