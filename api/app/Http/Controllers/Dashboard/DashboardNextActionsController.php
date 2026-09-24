<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\{Canal, Event, Venue, Message};
use App\Enums\AdmissionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardNextActionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $ids = $user->dashboardCanalIds();
        $events = Event::query()->whereIn('canal_id', $ids);
        $now = now();
        $upcoming = $user->can('event.view') ? (clone $events)->where('status', 'published')
            ->where('start_at', '>=', $now)->where('start_at', '<', $now->copy()->addDays(7))
            ->withCount(['admissions as registrations' => fn ($q) => $q->where('ticket_admissions.status', AdmissionStatus::Valid->value)])
            ->orderBy('start_at')->orderBy('id')->limit(5)->get()
            ->map(fn ($event) => ['id' => $event->id, 'name' => $event->name, 'start_at' => $event->start_at?->toIso8601String(), 'registrations' => (int) $event->registrations]) : [];
        $drafts = $user->can('event.view') ? (clone $events)->where('status', 'draft')->with('files')
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get()
            ->map(fn ($event) => [
                'id' => $event->id, 'name' => $event->name, 'can_edit' => Gate::allows('update', $event),
                'values' => ['name' => $event->name, 'body' => $event->body, 'start_at' => $event->start_at,
                    'venue_id' => $event->venue_id, 'website' => $event->website, 'email' => $event->email,
                    'phone' => $event->phone, 'image' => $event->has_primary_image],
            ]) : [];
        $canals = Canal::query()->whereIn('id', $ids)->whereNotIn('status', ['archived', 'blocked']);
        $activeIds = (clone $canals)->pluck('id');
        $hasVenue = Venue::query()->whereNotIn('status', ['archived', 'blocked'])
            ->whereHas('canals', fn ($q) => $q->whereIn('canals.id', $activeIds)->where('canal_venue.status', 'published'))->exists();
        return response()->json(['data' => [
            'upcoming' => $upcoming, 'drafts' => $drafts,
            'unread' => Message::query()->inboxOf($user->id)->whereNull('read_at')->count(),
            'checklist' => [
                ['kind' => 'canal', 'done' => $canals->exists(), 'can_create' => Gate::allows('create', Canal::class) && $user->can('canal.update')],
                ['kind' => 'venue', 'done' => $hasVenue, 'can_create' => Gate::allows('create', Venue::class)],
                ['kind' => 'event', 'done' => (clone $events)->whereNotIn('status', ['archived', 'blocked'])->exists(), 'can_create' => Gate::allows('create', Event::class)],
            ],
        ]]);
    }
}
