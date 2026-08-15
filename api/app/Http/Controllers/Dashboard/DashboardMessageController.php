<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Notifications\MessageReplied;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;

/**
 * Inbox prijatých správ (roadmap 3.4).
 *
 * Doteraz správa existovala len ako e-mail — v tabuľke sedela, ale nikde sa
 * nedala prečítať, a „neprečítané správy" v štatistikách odkazovali na nič.
 * Inbox je osobný (podľa `recipient_user_id`), nie per-kanálový: príjemcu určuje
 * `Messageable::messageRecipient()`, čo je vždy konkrétny človek.
 */
class DashboardMessageController extends Controller
{
    /** Zoznam prijatých správ — jeden riadok na vlákno, nie na správu. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:250'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = (int) $request->user()->id;

        $query = Message::query()
            ->inboxOf($userId)
            // Odpoveď sa v zozname neopakuje, keď je jej vlákno v inboxe už aj
            // tak (ukáže sa v detaile). Odpoveď na správu, ktorú som poslal ja,
            // ale vlastný koreň v inboxe nemá — tá riadok dostane.
            ->where(fn (Builder $q) => $q
                ->whereNull('parent_message_id')
                ->orWhereDoesntHave('parent', fn (Builder $parent) => $parent->where('recipient_user_id', $userId)))
            ->with(['sender', 'recipient', 'messageable'])
            ->latest('created_at')
            ->latest('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if (! empty($filters['search'])) {
            $query->where('body', 'like', '%' . addcslashes($filters['search'], '\\%_') . '%');
        }

        return MessageResource::collection(
            $query->paginate($filters['per_page'] ?? 15)
        );
    }

    /**
     * Počet neprečítaných pre odznak v menu. Vlastný endpoint, aby ho layout
     * mohol ťahať bez načítania celého zoznamu.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => Message::query()
                ->inboxOf((int) $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * Detail vlákna. Otvorenie označí za prečítané všetko, čo je v ňom
     * adresované mne — inak by odznak neprečítaných ostal svietiť nad správou,
     * ktorú mám práve na obrazovke. Späť na neprečítanú sa dá cez `markRead`.
     */
    public function show(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $root = $message->parent_message_id
            ? Message::query()->findOrFail($message->parent_message_id)
            : $message;

        $root->load(['sender', 'recipient', 'messageable', 'replies.sender', 'replies.recipient']);

        Message::query()
            ->whereIn('id', $root->replies->pluck('id')->push($root->id))
            ->inboxOf((int) $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(new MessageResource($root->refresh()->load([
            'sender', 'recipient', 'messageable', 'replies.sender', 'replies.recipient',
        ])));
    }

    /** Ručné prepnutie prečítané / neprečítané. */
    public function markRead(Request $request, Message $message): JsonResponse
    {
        $this->authorize('markRead', $message);

        $read = $request->validate(['read' => ['nullable', 'boolean']]);

        $message->forceFill([
            'read_at' => ($read['read'] ?? true) ? ($message->read_at ?? now()) : null,
        ])->save();

        return response()->json(new MessageResource(
            $message->load(['sender', 'recipient', 'messageable'])
        ));
    }

    /**
     * Odpoveď organizátora. Ukladá sa ako správa s otočeným odosielateľom
     * a príjemcom, zavesená na koreň vlákna.
     */
    public function reply(Request $request, Message $message): JsonResponse
    {
        $this->authorize('reply', $message);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $sender = $request->user();
        $recipient = $message->sender;

        if (! $recipient) {
            abort(422, __('messages.errors.sender_gone'));
        }

        $reply = Message::query()->create([
            'parent_message_id' => $message->threadRootId(),
            'messageable_type' => $message->messageable_type,
            'messageable_id' => $message->messageable_id,
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'body' => $data['body'],
        ]);

        // Neaktívny účet (neoverený e-mail, blokovaný) e-mail nedostane —
        // odpoveď mu aj tak ostane vo vlákne, keď sa prihlási.
        if ($recipient->canReceiveMessages()) {
            Notification::route('mail', $recipient->email)
                ->notify(new MessageReplied($reply, $sender->displayName(), (string) $sender->email));
        }

        return response()->json(new MessageResource(
            $reply->load(['sender', 'recipient', 'messageable'])
        ), 201);
    }
}
