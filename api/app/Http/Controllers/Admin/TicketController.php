<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Prehľad všetkých objednávok vstupeniek a rezervácií naprieč podujatiami.
 * Len čítanie — správa jednotlivých objednávok ostáva v detaile podujatia.
 */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'payment' => ['nullable', Rule::enum(TicketPaymentStatus::class)],
            'price' => ['nullable', 'in:free,paid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tickets = Ticket::query()
            ->with(['event:id,name,start_at', 'admissions:id,ticket_id,status,checked_in_at'])
            ->tap(fn (Builder $query) => $this->filter($query, $validated))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'createdAt' => $ticket->created_at?->toIso8601String(),
                'holderName' => $ticket->holder_name,
                'holderEmail' => $ticket->holder_email,
                'holderPhone' => $ticket->holder_phone,
                'userId' => $ticket->user_id,
                'status' => $ticket->status?->value,
                'statusLabel' => $ticket->status?->label(),
                'paymentStatus' => $ticket->payment_status?->value,
                'paymentStatusLabel' => $ticket->payment_status?->label(),
                'priceAmount' => $ticket->price_amount,
                'priceCurrency' => $ticket->price_currency,
                'admissionsTotal' => $ticket->admissions_total,
                'checkedInCount' => $ticket->checked_in_count,
                'event' => $ticket->event ? [
                    'id' => $ticket->event->id,
                    'name' => $ticket->event->name,
                    'startAt' => $ticket->event->start_at?->toIso8601String(),
                ] : null,
            ])->values(),
            'meta' => [
                'currentPage' => $tickets->currentPage(),
                'lastPage' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
            'summary' => $this->summary(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['payment'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when(($filters['price'] ?? null) === 'free', fn ($q) => $q->where(fn ($q) => $q->whereNull('price_amount')->orWhere('price_amount', 0)))
            ->when(($filters['price'] ?? null) === 'paid', fn ($q) => $q->where('price_amount', '>', 0))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $pattern = '%'.addcslashes($value, '%_\\').'%';
                $q->where(fn ($q) => $q
                    ->where('holder_name', 'like', $pattern)
                    ->orWhere('holder_email', 'like', $pattern)
                    ->orWhereHas('event', fn ($q) => $q->where('name', 'like', $pattern)));
            });
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $row = DB::table('tickets')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(created_at >= ?), 0) as day', [now()->subDay()])
            ->selectRaw('COALESCE(SUM(created_at >= ?), 0) as week', [now()->subDays(7)])
            ->selectRaw('COALESCE(SUM(price_amount > 0), 0) as paid')
            ->selectRaw('COALESCE(SUM(status = ?), 0) as cancelled', [TicketStatus::Cancelled->value])
            ->first();

        return [
            'total' => (int) $row->total,
            'day' => (int) $row->day,
            'week' => (int) $row->week,
            'paid' => (int) $row->paid,
            'cancelled' => (int) $row->cancelled,
        ];
    }
}
