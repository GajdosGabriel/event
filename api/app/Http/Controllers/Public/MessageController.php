<?php

namespace App\Http\Controllers\Public;

use App\Contracts\Messageable;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageStoreRequest;
use App\Models\Message;
use App\Notifications\MessageReceived;
use App\Services\Users\GuestAccountProvisioner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * Generické „Poslať správu" pre ľubovoľný Messageable cieľ (podujatie / miesto
 * / kanál…). Povolené typy drží whitelist Message::TARGETS, takže pridanie
 * ďalšieho typu je len o jeho zaradení tam (+ implementácia Messageable).
 */
class MessageController extends Controller
{
    public function __construct(
        private GuestAccountProvisioner $accounts,
    ) {
    }

    public function store(MessageStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $target = $this->resolveTarget($data['target_type'], (int) $data['target_id']);

        // Príjemcom je vlastník cieľa. Bez neho (alebo bez jeho e-mailu) nemá
        // správu kam doručiť; tlačidlo sa vtedy na fronte ani neukáže.
        $recipient = $target->messageRecipient();
        if (! $recipient) {
            abort(422, 'Tomuto cieľu nie je možné poslať správu.');
        }

        $sender = auth('sanctum')->user();

        if ($sender) {
            $senderName = $sender->pendingProfile?->display_name
                ?? strtok((string) $sender->email, '@');
            $senderEmail = $sender->email;
        } else {
            $senderName = $data['sender_name'];
            $senderEmail = $data['sender_email'];
            // E-mail bez účtu → založíme neaktívny účet (ako pri vstupenkách),
            // aby odosielateľ neskôr videl svoje správy a mohol sa aktivovať.
            $sender = $this->accounts->ensure($senderEmail, $senderName, 'message');
        }

        $message = $target->messages()->create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'body' => $data['body'],
        ]);

        Notification::route('mail', $recipient->email)
            ->notify(new MessageReceived($message, $senderName, (string) $senderEmail));

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
