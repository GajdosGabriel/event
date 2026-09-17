<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Prehľad spotreby OpenAI (tabuľka ai_usages). Len čítanie — nič sa tu
 * nezapína ani nevypína, stránka má ukázať, čo koľko berie.
 *
 * Zostatok kreditu cez API s bežným kľúčom zistiť nejde; cena je odhad
 * z tokenov a cenníka v config/openai.php.
 */
class AiUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:1,7,30,90,365'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $period = fn (): Builder => DB::table('ai_usages')->where('created_at', '>=', $from);

        return response()->json(['data' => [
            'days' => $days,
            'totals' => [
                'period' => $this->totals($period()),
                'month' => $this->totals(DB::table('ai_usages')->where('created_at', '>=', now()->startOfMonth())),
                'all' => $this->totals(DB::table('ai_usages')),
            ],
            'byFeature' => $this->grouped($period(), 'feature'),
            'bySource' => $this->grouped($period(), 'source', 15),
            'byCanal' => $this->byCanal($period()),
            'byUser' => $this->byUser($period()),
            'byDay' => $period()
                ->selectRaw('DATE(created_at) as day')
                ->selectRaw($this->sums())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('day')
                ->get()
                ->map(fn ($row) => ['day' => (string) $row->day] + $this->row($row)),
            'recent' => AiUsage::query()
                ->with(['canal:id,name', 'user:id,email'])
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (AiUsage $usage) => [
                    'id' => $usage->id,
                    'createdAt' => $usage->created_at?->toIso8601String(),
                    'feature' => $usage->feature,
                    'source' => $usage->source,
                    'model' => $usage->model,
                    'promptTokens' => $usage->prompt_tokens,
                    'completionTokens' => $usage->completion_tokens,
                    'costUsd' => round($usage->cost_usd, 6),
                    'success' => $usage->success,
                    'canal' => $usage->canal ? ['id' => $usage->canal->id, 'name' => $usage->canal->name] : null,
                    'user' => $usage->user ? ['id' => $usage->user->id, 'name' => $usage->user->email] : null,
                    'subjectType' => $usage->subject_type,
                    'subjectId' => $usage->subject_id,
                ]),
        ]]);
    }

    private function sums(): string
    {
        return 'COUNT(*) as calls, '
            .'COALESCE(SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END), 0) as failed, '
            .'COALESCE(SUM(prompt_tokens), 0) as prompt_tokens, '
            .'COALESCE(SUM(completion_tokens), 0) as completion_tokens, '
            .'COALESCE(SUM(cost_usd), 0) as cost_usd';
    }

    /** @return array{calls: int, failed: int, promptTokens: int, completionTokens: int, costUsd: float} */
    private function row(object $row): array
    {
        return [
            'calls' => (int) $row->calls,
            'failed' => (int) $row->failed,
            'promptTokens' => (int) $row->prompt_tokens,
            'completionTokens' => (int) $row->completion_tokens,
            'costUsd' => round((float) $row->cost_usd, 6),
        ];
    }

    /** @return array{calls: int, failed: int, promptTokens: int, completionTokens: int, costUsd: float} */
    private function totals(Builder $query): array
    {
        return $this->row($query->selectRaw($this->sums())->first());
    }

    /** @return list<array<string, mixed>> */
    private function grouped(Builder $query, string $column, ?int $limit = null): array
    {
        return $query
            ->select($column)
            ->selectRaw($this->sums())
            ->groupBy($column)
            ->orderByDesc('cost_usd')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(fn ($row) => ['key' => $row->{$column}] + $this->row($row))
            ->values()
            ->all();
    }

    /**
     * Kanál je vyplnený len pri volaniach s predmetom (štítkovanie, kontrola
     * obsahu, AI detektor podujatí). Zvyšok ide do riadku s kanálom null.
     *
     * @return list<array<string, mixed>>
     */
    private function byCanal(Builder $query): array
    {
        $rows = $this->grouped($query, 'canal_id', 20);
        $names = Canal::withTrashed()
            ->whereIn('id', array_filter(array_column($rows, 'key')))
            ->pluck('name', 'id');

        return array_map(fn (array $row) => $row + ['name' => $row['key'] ? ($names[$row['key']] ?? null) : null], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function byUser(Builder $query): array
    {
        $rows = $this->grouped($query, 'user_id', 20);
        $users = User::query()
            ->whereIn('id', array_filter(array_column($rows, 'key')))
            ->get(['id', 'email'])
            ->keyBy('id');

        return array_map(function (array $row) use ($users) {
            $user = $row['key'] ? $users->get($row['key']) : null;

            return $row + ['name' => $user?->email];
        }, $rows);
    }
}
