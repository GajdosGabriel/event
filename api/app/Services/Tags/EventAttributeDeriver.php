<?php

namespace App\Services\Tags;

use App\Enums\TagGroup;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Odvodí štítky facetu „charakter" z dát, nie z AI.
 *
 * Merania na reálnych podujatiach ukázali, že model tento facet halucinuje aj
 * pri explicitnom zákaze: fyzickej púti priradil „online" (lebo pozvánka je na
 * webe) a „vstup voľný" (lebo cena nebola spomenutá) s istotou nad 70.
 *
 * Pritom sú to fakty, ktoré systém vie presne: dĺžku podujatia z termínu,
 * registráciu z typov lístkov, cenu z price_amount. Zvyšné dva („vonku",
 * „online") sú viazané na doslovné slová v texte, takže stačí kľúčové slovo.
 *
 * Vedľajší efekt: štítky sa opravia hneď pri úprave termínu alebo ceny, bez
 * čakania na ďalší AI beh, a nestoja nič.
 */
class EventAttributeDeriver
{
    private const SOURCE = 'derived';

    /**
     * Termíny sú v DB v UTC (config app.timezone = UTC), ale „koľko dní to
     * trvá" je otázka o miestnom čase. Jednodňová akcia 5. 9. sa v lete uloží
     * ako 4. 9. 22:00 → 5. 9. 21:59 UTC a bez prepočtu by vyzerala ako dvojdňová
     * (na reálnych dátach to robilo 56 z 91 podujatí).
     */
    private const DISPLAY_TIMEZONE = 'Europe/Bratislava';

    /** Podujatie sa koná na diaľku — musí to povedať text, nedá sa odvodiť. */
    private const ONLINE_PATTERN = '/\b(online|on-line|naživo|na živo|livestream|live stream|stream|webinár|webinar|zoom|ms teams|videokonferenc|virtuáln)/iu';

    /** Pod holým nebom. */
    private const OUTDOOR_PATTERN = '/\b(vonku|open ?air|openair|amfiteát|amfiteat|pod holým nebom|na námestí|námestie|v parku|nádvor|záhrad|ihrisk|štadión|kalvári)/iu';

    /**
     * Prepočíta a uloží odvodené štítky. Vracia priradené slugy.
     *
     * @return array<int, string>
     */
    public function sync(Event $event): array
    {
        $slugs = $this->derive($event);

        $slugToId = Tag::query()
            ->active()
            ->inGroup(TagGroup::Attribute)
            ->pluck('id', 'slug');

        DB::transaction(function () use ($event, $slugs, $slugToId) {
            // Ručný výber človeka je nedotknuteľný — rovnako ako pri AI.
            $manualIds = DB::table('event_tag')
                ->where('event_id', $event->id)
                ->whereNotIn('source', [self::SOURCE])
                ->pluck('tag_id')
                ->all();

            DB::table('event_tag')
                ->where('event_id', $event->id)
                ->where('source', self::SOURCE)
                ->delete();

            $payload = [];

            foreach ($slugs as $slug) {
                $tagId = $slugToId->get($slug);

                if ($tagId === null || in_array((int) $tagId, $manualIds, true)) {
                    continue;
                }

                $payload[(int) $tagId] = [
                    'confidence' => 100,
                    'source' => self::SOURCE,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($payload !== []) {
                $event->tags()->attach($payload);
            }
        });

        return $slugs;
    }

    /**
     * @return array<int, string>
     */
    public function derive(Event $event): array
    {
        $slugs = [];

        if ($this->isMultiDay($event)) {
            $slugs[] = 'viacdnove';
        }

        if ($this->requiresRegistration($event)) {
            $slugs[] = 's-registraciou';
        }

        if ($this->isFree($event)) {
            $slugs[] = 'vstup-volny';
        }

        $text = $this->text($event);

        if (preg_match(self::ONLINE_PATTERN, $text) === 1) {
            $slugs[] = 'online';
        }

        if (preg_match(self::OUTDOOR_PATTERN, $text) === 1) {
            $slugs[] = 'vonku';
        }

        return $slugs;
    }

    private function isMultiDay(Event $event): bool
    {
        if ($event->start_at === null || $event->end_at === null) {
            return false;
        }

        return ! $event->start_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)
            ->isSameDay($event->end_at->copy()->setTimezone(self::DISPLAY_TIMEZONE));
    }

    private function requiresRegistration(Event $event): bool
    {
        return (bool) $event->tickets_enabled;
    }

    /**
     * Bezplatné je len to, čo je ako bezplatné vyplnené. Chýbajúca cena
     * znamená „nevieme", nie „zadarmo" — presne na tomto sa mýlil model.
     */
    private function isFree(Event $event): bool
    {
        if ($event->price_amount !== null && (int) $event->price_amount === 0) {
            return true;
        }

        $activeTypes = $event->relationLoaded('ticketTypes')
            ? $event->ticketTypes->where('is_active', true)
            : $event->ticketTypes()->where('is_active', true)->get();

        return $activeTypes->isNotEmpty()
            && $activeTypes->every(fn ($type) => $type->price_amount !== null && (int) $type->price_amount === 0);
    }

    private function text(Event $event): string
    {
        return (string) $event->name . ' ' . strip_tags((string) ($event->body ?? ''));
    }
}
