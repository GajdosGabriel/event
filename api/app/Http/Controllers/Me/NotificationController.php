<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);

        return NotificationResource::collection(
            $request->user()->notifications()->orderByDesc('created_at')->orderByDesc('id')->paginate(15)
        )->additional(['meta' => ['unread' => $request->user()->unreadNotifications()->count()]]);
    }

    public function count(Request $request)
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request)
    {
        $this->selection($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function markUnread(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1|max:500']);
        $this->selection($request)->whereNotNull('read_at')->update(['read_at' => null]);

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $this->selection($request)->delete();

        return response()->noContent();
    }

    private function selection(Request $request)
    {
        $data = $request->validate([
            'ids' => 'sometimes|required|array|min:1|max:500',
            'ids.*' => 'required|uuid|distinct',
            'only' => 'sometimes|required|in:read',
        ]);
        // Every operation is scoped to the authenticated owner, including bulk actions.
        $query = $request->user()->notifications();
        if (isset($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }
        if (($data['only'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        }

        return $query;
    }
}
