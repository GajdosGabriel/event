<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\SupportCategory;
use App\Enums\SupportStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreated;
use App\Notifications\SupportTicketReplied;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Podpora — vlákna používateľa s administrátormi platformy.
 *
 * Používateľ vidí len svoje vlákna (sekcia Správy → Podpora); super-admin
 * s `all=1` vidí všetky (Admin → Podpora) a odpovedá v mene podpory. Každá
 * nová správa ide druhej strane do zvončeka a e-mailom.
 *
 * Zámerne mimo MessagePolicy / Gate: v dashboarde super-admin bypass nemá
 * (AuthServiceProvider), rolu preto kontrolujeme priamo tu.
 */
class DashboardSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'all' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(SupportStatus::values())],
            'search' => ['nullable', 'string', 'max:250'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        // Admin si prepína medzi „moje" a schránkou podpory; bez `all` vidí
        // aj on len vlastné vlákna, ako ktokoľvek iný.
        $all = $request->boolean('all') && $this->isStaff($user);

        $tickets = SupportTicket::query()
            ->with(['user', 'canal:id,name', 'latestMessage'])
            ->withCount('messages')
            ->when(! $all, fn (Builder $q) => $q->where('user_id', $user->id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $term = '%' . addcslashes($search, '\\%_') . '%';
                $q->where(fn (Builder $w) => $w
                    ->where('subject', 'like', $term)
                    ->orWhereHas('messages', fn (Builder $m) => $m->where('body', 'like', $term))
                    ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', $term)));
            })
            // Hore to, čo čaká na toho, kto sa pozerá: podpora rieši otvorené,
            // používateľ odpovedané.
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', $all
                ? [SupportStatus::Open->value, SupportStatus::Answered->value]
                : [SupportStatus::Answered->value, SupportStatus::Open->value])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => collect($tickets->items())->map(fn (SupportTicket $t) => $this->present($t, $user))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'categories' => SupportCategory::options(),
                'counts' => $all ? $this->counts() : null,
                'is_staff' => $this->isStaff($user),
            ],
        ]);
    }

    /**
     * Odznaky: koľko mojich vlákien má nepozretú odpoveď podpory a — pre
     * super-admina — koľko vlákien čaká na podporu.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => self::unreadFor($user),
            'inbox' => $this->isStaff($user)
                ? SupportTicket::query()->where('status', SupportStatus::Open->value)->count()
                : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'category' => ['required', Rule::in(SupportCategory::values())],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'canal_id' => ['nullable', 'integer'],
        ]);

        // Kontext len z kanálov, kde človek naozaj je — inak by si mohol
        // k vláknu „prilepiť" cudzí kanál.
        $canalId = isset($data['canal_id']) && $user->dashboardCanalIds()->contains((int) $data['canal_id'])
            ? (int) $data['canal_id']
            : null;

        $ticket = DB::transaction(function () use ($data, $user, $request, $canalId) {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id,
                'canal_id' => $canalId,
                'category' => $data['category'],
                'subject' => trim($data['subject']),
                'status' => SupportStatus::Open,
                'page_url' => $data['page_url'] ?? null,
                'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
                'last_activity_at' => now(),
                'user_seen_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $user->id,
                'is_staff' => false,
                'body' => trim($data['body']),
            ]);

            return $ticket;
        });

        Notification::send($this->staff()->reject(fn (User $u) => $u->is($user)), new SupportTicketCreated($ticket));

        return response()->json($this->present($this->loadThread($ticket), $user, true), 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTicket($user, $supportTicket);

        // Otvorenie vlákna je jeho prečítanie — aj upozornenia k nemu už netreba.
        $supportTicket->forceFill([$this->seenColumn($user, $supportTicket) => now()])->save();
        $user->unreadNotifications()
            ->whereIn('data->link', ['/dashboard/spravy/podpora/' . $supportTicket->id, '/admin/podpora/' . $supportTicket->id])
            ->update(['read_at' => now()]);

        return response()->json($this->present($this->loadThread($supportTicket), $user, true));
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTicket($user, $supportTicket);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        // Vlastník píše ako používateľ aj vtedy, keď je náhodou super-admin.
        $asStaff = (int) $supportTicket->user_id !== (int) $user->id;

        $message = DB::transaction(function () use ($supportTicket, $user, $data, $asStaff) {
            $message = $supportTicket->messages()->create([
                'user_id' => $user->id,
                'is_staff' => $asStaff,
                'body' => trim($data['body']),
            ]);

            // Odpoveď vlákno vždy otvorí — aj zatvorené, keď sa k nemu niekto
            // vráti. Druhá strana tým dostane „nové".
            $supportTicket->forceFill([
                'status' => $asStaff ? SupportStatus::Answered : SupportStatus::Open,
                'last_activity_at' => now(),
                'user_seen_at' => $asStaff ? null : now(),
                'staff_seen_at' => $asStaff ? now() : null,
            ])->save();

            return $message;
        });

        $notification = new SupportTicketReplied($message);

        if ($asStaff) {
            $supportTicket->user?->notify($notification);
        } else {
            Notification::send($this->staffFor($supportTicket, $user), $notification);
        }

        return response()->json($this->present($this->loadThread($supportTicket), $user, true));
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTicket($user, $supportTicket);

        $data = $request->validate([
            'status' => ['required', Rule::in([SupportStatus::Open->value, SupportStatus::Closed->value])],
        ]);

        $supportTicket->forceFill(['status' => $data['status'], 'last_activity_at' => now()])->save();

        return response()->json($this->present($this->loadThread($supportTicket), $user, true));
    }

    /** Počet mojich vlákien s nepozretou odpoveďou podpory (aj pre odznak Správ). */
    public static function unreadFor(User $user): int
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->whereNull('user_seen_at')
            ->count();
    }

    /**
     * Komu dať vedieť o doplnení od používateľa: tomu z podpory, kto vo vlákne
     * odpovedal naposledy — ak nikto (alebo už nie je adminom), všetkým.
     *
     * @return Collection<int, User>
     */
    private function staffFor(SupportTicket $ticket, User $sender): Collection
    {
        $last = $ticket->messages()->where('is_staff', true)->latest('id')->first()?->author;

        if ($last && ! $last->trashed() && ! $last->is($sender) && $this->isStaff($last)) {
            return collect([$last]);
        }

        return $this->staff()->reject(fn (User $u) => $u->is($sender));
    }

    /** @return Collection<int, User> */
    private function staff(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'super-admin'))
            ->get();
    }

    private function isStaff(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    private function authorizeTicket(User $user, SupportTicket $ticket): void
    {
        // 404, nie 403: cudzie vlákno nesmie ani prezradiť, že existuje.
        abort_unless((int) $ticket->user_id === (int) $user->id || $this->isStaff($user), 404);
    }

    private function seenColumn(User $user, SupportTicket $ticket): string
    {
        return (int) $ticket->user_id === (int) $user->id ? 'user_seen_at' : 'staff_seen_at';
    }

    private function loadThread(SupportTicket $ticket): SupportTicket
    {
        return $ticket->refresh()->load(['messages.author', 'user', 'canal:id,name'])->loadCount('messages');
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = SupportTicket::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(SupportStatus::values())
            ->mapWithKeys(fn (string $s) => [$s => (int) ($counts[$s] ?? 0)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $ticket, User $viewer, bool $withMessages = false): array
    {
        $mine = (int) $ticket->user_id === (int) $viewer->id;
        $viewerIsStaff = ! $mine && $this->isStaff($viewer);
        $latest = $withMessages ? $ticket->messages->last() : $ticket->latestMessage;
        $seenAt = $mine ? $ticket->user_seen_at : $ticket->staff_seen_at;

        $data = [
            'id' => $ticket->id,
            'reference' => $ticket->reference(),
            'subject' => $ticket->subject,
            'category' => ['value' => $ticket->category->value, 'label' => $ticket->category->label()],
            'status' => ['value' => $ticket->status->value, 'label' => $ticket->status->label()],
            'mine' => $mine,
            'unread' => $seenAt === null,
            'user_name' => $ticket->user?->displayName() ?? '—',
            'canal' => $ticket->canal ? ['id' => $ticket->canal->id, 'name' => $ticket->canal->name] : null,
            'messages_count' => $ticket->messages_count ?? null,
            'excerpt' => $latest ? Str::limit($latest->body, 140) : null,
            'last_from_staff' => (bool) $latest?->is_staff,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'last_activity_at' => ($ticket->last_activity_at ?? $ticket->updated_at)?->toIso8601String(),
        ];

        if ($viewerIsStaff) {
            // Kontakt a technické detaily len pre podporu, nie späť používateľovi.
            $data['user_email'] = $ticket->user?->email;
            $data['user_id'] = $ticket->user_id;
        }

        if ($withMessages) {
            $data['page_url'] = $ticket->page_url;
            $data['user_agent'] = $viewerIsStaff ? $ticket->user_agent : null;
            $data['messages'] = $ticket->messages->map(fn (SupportMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_staff' => $m->is_staff,
                'mine' => (int) $m->user_id === (int) $viewer->id,
                // Používateľ vidí podporu ako „Podpora", nie konkrétneho admina.
                'author' => $m->is_staff && ! $viewerIsStaff
                    ? __('support.staff_name')
                    : ($m->author?->displayName() ?? '—'),
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
