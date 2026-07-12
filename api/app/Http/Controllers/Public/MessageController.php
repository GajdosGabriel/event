<?php

namespace App\Http\Controllers\Public;

use App\Contracts\Messageable;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageStoreRequest;
use App\Models\Message;
use App\Notifications\MessageReceived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * Generické „Poslať správu" pre ľubovoľný Messageable cieľ (podujatie / miesto
 * / kanál…). Povolené typy drží whitelist Message::TARGETS, takže pridanie
 * ďalšieho typu je len o jeho zaradení tam (+ implementácia Messageable).
 *
 * Anti-spam: posielať môžu len prihlásení používatelia s overeným e-mailom.
 * Hostia formulár nevidia — front ich vyzve na registráciu.
 */
class MessageController extends Controller
{
    public function store(MessageStoreRequest $request): JsonResponse
    {
        // Odosielateľ: len prihlásený (401) a overený, neblokovaný účet (403).
        $sender = auth('sanctum')->user();

        if (! $sender) {
            abort(401, 'Na poslanie správy sa musíte prihlásiť.');
        }

        if (! $sender->canSendMessages()) {
            abort(403, 'Správy môžu posielať len účty s overeným e-mailom.');
        }

        $data = $request->validated();

        $target = $this->resolveTarget($data['target_type'], (int) $data['target_id']);

        // Príjemcom je vlastník cieľa. Bez aktívneho vlastníka (alebo pri
        // importovanom obsahu) nemá správu kam doručiť; tlačidlo sa vtedy na
        // fronte ani neukáže.
        $recipient = $target->messageRecipient();
        if (! $recipient) {
            abort(422, 'Tomuto cieľu nie je možné poslať správu.');
        }

        if ($recipient->is($sender)) {
            abort(422, 'Nemôžete poslať správu sami sebe.');
        }

        $senderName = $sender->pendingProfile?->display_name
            ?? $sender->canal?->name
            ?? strtok((string) $sender->email, '@');

        $message = $target->messages()->create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'body' => $data['body'],
        ]);

        Notification::route('mail', $recipient->email)
            ->notify(new MessageReceived($message, $senderName, (string) $sender->email));

        return response()->json(['status' => 'ok'], 201);
    }

    /**
     * Nájde cieľ podľa aliasu z whitelistu a overí, že je Messageable.
     */
    private function resolveTarget(string $type, int $id): Messageable&Model
    {
        $class = Message::TARGETS[$type] ?? null;

        if (! $class || ! is_subclass_of($class, Messageable::class)) {
            abort(422, 'Neznámy typ cieľa správy.');
        }

        return $class::query()->findOrFail($id);
    }
}
