<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\Event;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Denník udalostí — čo komu odišlo, čo zlyhalo, prihlásenia, importy, cron.
 * Len čítanie. Zapisuje App\Listeners\SystemLogSubscriber a
 * App\Services\SystemLog\Recorder, staré záznamy maže model:prune.
 */
class SystemLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:32'],
            'level' => ['nullable', Rule::in(SystemLog::LEVELS)],
            'status' => ['nullable', Rule::in(SystemLog::STATUSES)],
            'search' => ['nullable', 'string', 'max:191'],
            'recipient' => ['nullable', 'string', 'max:191'],
            'user_id' => ['nullable', 'integer'],
            'canal_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = SystemLog::query()
            ->with('user:id,email')
            ->tap(fn (Builder $query) => $this->filter($query, $validated))
            ->orderByDesc('id')
            ->paginate(50);

        $links = $this->links($logs->getCollection());

        return response()->json([
            'data' => $logs->getCollection()->map(fn (SystemLog $log) => [
                'id' => $log->id,
                'createdAt' => $log->created_at?->toIso8601String(),
                'level' => $log->level,
                'channel' => $log->channel,
                'event' => $log->event,
                'status' => $log->status,
                'message' => $log->message,
                'recipient' => $log->recipient,
                'user' => $log->user_id ? ['id' => $log->user_id, 'email' => $log->user?->email] : null,
                'subjectType' => $log->subject_type ? class_basename($log->subject_type) : null,
                'subjectId' => $log->subject_id,
                'ip' => $log->ip,
                'context' => $log->context,
                'links' => $links[$log->id] ?? [],
            ])->values(),
            'meta' => [
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'summary' => $this->summary(),
            'channels' => DB::table('system_logs')->distinct()->orderBy('channel')->pluck('channel'),
            'retention' => [
                'days' => (int) config('logging.system_log.days'),
                'errorDays' => (int) config('logging.system_log.error_days'),
            ],
        ]);
    }

    /**
     * Kľúče v `context`, ktoré ukazujú na iný záznam, a ich úloha v udalosti.
     * Prvá položka je typ odkazu (canal|event|venue|user|claim).
     */
    private const CONTEXT_LINKS = [
        'canal_id' => ['canal', 'canal'],
        'event_id' => ['event', 'event'],
        'member_id' => ['user', 'member'],
        'actor_id' => ['user', 'actor'],
        'removed_technical_owner_id' => ['user', 'removed'],
        'invited_by_user_id' => ['user', 'inviter'],
        'claim_id' => ['claim', 'claim'],
    ];

    /**
     * Pre každý záznam zoznam odkazov na entity, o ktorých je — kanál,
     * podujatie, miesto, používatelia (člen, kto zmenu urobil, príjemca) —
     * aby sa z denníka dalo preklikať na ich detail. Mená sa dočítavajú
     * hromadne, jedným dotazom na typ, nie po riadkoch.
     *
     * @param  \Illuminate\Support\Collection<int, SystemLog>  $logs
     * @return array<int, list<array{type: string, id: int, label: string, role: string}>>
     */
    private function links($logs): array
    {
        $refs = [];

        foreach ($logs as $log) {
            $found = [];

            if ($log->subject_type && $log->subject_id) {
                $type = strtolower(class_basename($log->subject_type));
                if (in_array($type, ['canal', 'event', 'venue', 'user'], true)) {
                    $found[] = [$type, (int) $log->subject_id, 'subject'];
                }
            }
            if ($log->user_id) {
                $found[] = ['user', (int) $log->user_id, 'user'];
            }
            foreach (self::CONTEXT_LINKS as $key => [$type, $role]) {
                $id = $log->context[$key] ?? null;
                if (is_numeric($id)) {
                    $found[] = [$type, (int) $id, $role];
                }
            }

            $refs[$log->id] = $found;
        }

        $ids = fn (string $type) => collect($refs)->flatten(1)->where(0, $type)->pluck(1)->unique()->values();

        $names = [
            'canal' => Canal::withTrashed()->whereIn('id', $ids('canal'))->pluck('name', 'id'),
            'event' => Event::withTrashed()->whereIn('id', $ids('event'))->pluck('name', 'id'),
            'venue' => Venue::withTrashed()->whereIn('id', $ids('venue'))->pluck('name', 'id'),
            'user' => User::withTrashed()->whereIn('id', $ids('user'))->pluck('email', 'id'),
        ];

        // E-mail príjemcu, ktorý patrí účtu, vedie na tento účet.
        $recipients = $logs->pluck('recipient')->filter()->unique()->values();
        $userByEmail = $recipients->isEmpty()
            ? collect()
            : User::withTrashed()->whereIn('email', $recipients)->pluck('id', 'email');

        $result = [];

        foreach ($logs as $log) {
            $seen = [];
            $row = [];

            $add = function (string $type, int $id, string $role) use (&$seen, &$row, $names) {
                if (isset($seen[$type.$id])) {
                    return;
                }
                $seen[$type.$id] = true;
                $label = $type === 'claim' ? '#'.$id : ($names[$type][$id] ?? null);
                $row[] = [
                    'type' => $type,
                    'id' => $id,
                    'label' => (string) ($label ?? '#'.$id),
                    'role' => $role,
                ];
            };

            foreach ($refs[$log->id] as [$type, $id, $role]) {
                $add($type, $id, $role);
            }
            if ($log->recipient && isset($userByEmail[$log->recipient])) {
                $add('user', (int) $userByEmail[$log->recipient], 'recipient');
            }

            $result[$log->id] = $row;
        }

        return $result;
    }

    /** @param array<string, mixed> $filters */
    private function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['channel'] ?? null, fn ($q, $value) => $q->where('channel', $value))
            ->when($filters['level'] ?? null, fn ($q, $value) => $q->where('level', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['recipient'] ?? null, fn ($q, $value) => $q->where('recipient', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $pattern = '%'.addcslashes($value, '%_\\').'%';
                $q->where(fn ($q) => $q->where('recipient', 'like', $pattern)->orWhere('message', 'like', $pattern));
            })
            // Všetko o jednom človeku. Odoslané maily nesú len adresu, preto
            // aj zhoda s jeho e-mailom.
            ->when($filters['user_id'] ?? null, function ($q, $userId) {
                $email = User::withTrashed()->whereKey($userId)->value('email');
                $q->where(fn ($q) => $q
                    ->where('user_id', $userId)
                    ->when($email, fn ($q) => $q->orWhere('recipient', $email)));
            })
            // Všetko o jednom kanáli — to isté, čo ukazuje „História" na
            // detaile kanála (Admin\CanalController::history).
            ->when($filters['canal_id'] ?? null, function ($q, $canalId) {
                $email = trim((string) Canal::withTrashed()->whereKey($canalId)->value('email'));
                $q->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('subject_type', (new Canal)->getMorphClass())->where('subject_id', $canalId))
                    ->when($email !== '', fn ($q) => $q->orWhere('recipient', $email)));
            });
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $day = now()->subDay();

        $row = DB::table('system_logs')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("COALESCE(SUM(event = 'mail.sent' AND created_at >= ?), 0) as sent_day", [$day])
            ->selectRaw("COALESCE(SUM(event = 'mail.sent'), 0) as sent_week")
            ->selectRaw("COALESCE(SUM(event = 'mail.failed' AND created_at >= ?), 0) as failed_day", [$day])
            ->selectRaw("COALESCE(SUM(event = 'mail.failed'), 0) as failed_week")
            ->selectRaw("COALESCE(SUM(level = 'error' AND created_at >= ?), 0) as errors_day", [$day])
            ->selectRaw("COALESCE(SUM(level = 'error'), 0) as errors_week")
            ->selectRaw("COALESCE(SUM(event = 'auth.login' AND created_at >= ?), 0) as logins_day", [$day])
            ->selectRaw("COALESCE(SUM(event IN ('auth.failed', 'auth.lockout') AND created_at >= ?), 0) as auth_failed_day", [$day])
            ->first();

        return [
            'sentDay' => (int) $row->sent_day,
            'sentWeek' => (int) $row->sent_week,
            'failedDay' => (int) $row->failed_day,
            'failedWeek' => (int) $row->failed_week,
            'errorsDay' => (int) $row->errors_day,
            'errorsWeek' => (int) $row->errors_week,
            'loginsDay' => (int) $row->logins_day,
            'authFailedDay' => (int) $row->auth_failed_day,
        ];
    }
}
