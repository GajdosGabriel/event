<?php

namespace App\Services\Canals;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\CanalFollowStarted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sledovanie organizátora — jediné miesto, ktoré ho zakladá.
 *
 * Dve cesty:
 *
 * - **Výslovne** — tlačidlo „Sledovať organizátora". Platí hneď, bez
 *   potvrdzovacieho e-mailu: ľudia potvrdenie väčšinou neotvoria a radšej
 *   sa neskôr odhlásia, než by ho museli hľadať. Pošle sa jeden uvítací
 *   e-mail s odkazom na odhlásenie — ten je zároveň jediná obrana toho, komu
 *   adresu zadal niekto iný.
 * - **Automaticky** — kto si na akciu kanála rezervoval miesto, kúpil lístok
 *   alebo k nej poslal otázku, dostane o ďalších akciách tohto organizátora
 *   e-mail. Bez uvítacieho e-mailu (o vzťahu k organizátorovi už vie) a bez
 *   pýtania; formulár to oznamuje a každý súhrn má odkaz na odhlásenie.
 *
 * Výslovné odhlásenie platí natrvalo: automatická cesta adresu, ktorá sa od
 * kanála už raz odhlásila, znova neprihlási (porovnáva sa odtlačok, adresa sa
 * pri odhlásení maže). Tlačidlo áno — to je nové výslovné rozhodnutie.
 */
class CanalFollowing
{
    public function follow(Canal $canal, string $email, ?string $locale = null): Subscription
    {
        $email = mb_strtolower(trim($email));

        $subscription = $this->activeFor($canal->id, $email) ?? Subscription::query()->forceCreate([
            'subscribable_type' => Canal::class,
            'subscribable_id' => $canal->id,
            'email' => $email,
            'token' => Subscription::freshToken(),
            'locale' => $locale,
            'confirmed_at' => now(),
        ]);

        if ($subscription->wasRecentlyCreated) {
            $notification = new CanalFollowStarted($canal, $subscription);

            if ($locale) {
                $notification->locale($locale);
            }

            Notification::route('mail', $email)->notify($notification);
        }

        return $subscription;
    }

    /**
     * Automatické sledovanie organizátora akcie. Nikdy nevyhodí výnimku —
     * rezervácia ani otázka nesmie padnúť na tom, že sa nepodarilo založiť
     * doplnkový odber.
     */
    public function followAfterInteraction(Event $event, ?string $email, ?string $locale = null): void
    {
        try {
            $email = mb_strtolower(trim((string) $email));

            if ($event->canal_id === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            $canal = Canal::query()->find($event->canal_id);

            // Skrytý či blokovaný kanál nemá verejný profil, a teda ani akcie,
            // o ktorých by sme mohli písať.
            if ($canal === null || ! in_array((string) $canal->status?->value, ModelStatus::publiclyReadableValues(), true)) {
                return;
            }

            if ($this->activeFor($canal->id, $email) !== null || $this->hasUnsubscribed($canal->id, $email)) {
                return;
            }

            Subscription::query()->forceCreate([
                'subscribable_type' => Canal::class,
                'subscribable_id' => $canal->id,
                'email' => $email,
                'token' => Subscription::freshToken(),
                'locale' => $locale,
                'confirmed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CanalFollowing: automatické sledovanie zlyhalo', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function activeFor(int $canalId, string $email): ?Subscription
    {
        return Subscription::query()
            ->active()
            ->where('subscribable_type', Canal::class)
            ->where('subscribable_id', $canalId)
            ->where('email', $email)
            ->first();
    }

    private function hasUnsubscribed(int $canalId, string $email): bool
    {
        return Subscription::query()
            ->where('subscribable_type', Canal::class)
            ->where('subscribable_id', $canalId)
            ->where('email_hash', Subscription::hashEmail($email))
            ->whereNotNull('unsubscribed_at')
            ->exists();
    }
}
