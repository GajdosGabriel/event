<?php

namespace App\Http\Resources;

use App\Enums\QuestionBoardPhase;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Otázka pre verejnú nástenku aj pre moderovanie v dashboarde.
 *
 * `status` a `author_hash` sa verejne neposielajú: stav prezrádza, že niečo
 * visí v moderácii, a pseudonym pisateľa nemá server opustiť vôbec (viď
 * Question::$hidden). Moderačné pole zapína `withModeration()`, aby si verejná
 * cesta nemohla omylom vypýtať viac.
 *
 * Nie je tu žiadny príznak „za túto som hlasoval" — hlas je viazaný na token
 * v localStorage prehliadača, takže si to front vie povedať sám a nemusí ho
 * pri každom načítaní posielať na server.
 */
class QuestionResource extends JsonResource
{
    private bool $moderation = false;

    private ?Event $event = null;

    /**
     * Moderačný pohľad. `$event` je nepovinné — slúži len na to, aby sa dalo
     * povedať „toto prišlo počas akcie", teda že súkromný vstup nie je otázka
     * do FAQ, ale podnet, ktorý mal niekto riešiť vtedy.
     */
    public function withModeration(?Event $event = null): self
    {
        $this->moderation = true;
        $this->event = $event;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'body' => $this->body,
            'author_name' => $this->author_name,
            'upvotes_count' => (int) $this->upvotes_count,
            'answer_body' => $this->answer_body,
            'answered_at' => $this->answered_at,
            'highlighted' => $this->highlighted_at !== null,
            'created_at' => $this->created_at,
        ];

        if ($this->moderation) {
            $data['status'] = $this->status?->value;
            $data['status_label'] = $this->status?->label();
            $data['visibility'] = $this->visibility?->value;
            // Prišlo to počas akcie? Potom je to podnet („v sále je zima"),
            // nie otázka do FAQ — a v zozname to musí byť vidieť na prvý
            // pohľad, lebo podnet sa rieši teraz alebo nikdy.
            $data['live'] = $this->arrivedLive();
            // Či po odpovedi odíde e-mail. Samotná adresa von nejde nikdy
            // (Question::$hidden) — organizátorovi stačí vedieť, že píše
            // človeku, ktorý odpoveď dostane do schránky.
            $data['notifies_author'] = $this->resource->wantsAnswerNotification();
        }

        return $data;
    }

    /**
     * Prišla otázka v čase, keď podujatie bežalo? Bez znalosti podujatia sa to
     * povedať nedá — vtedy radšej nič než domnienka.
     */
    private function arrivedLive(): bool
    {
        return $this->event !== null
            && QuestionBoardPhase::for($this->event, $this->created_at)->isLive();
    }
}
