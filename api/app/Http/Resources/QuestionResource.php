<?php

namespace App\Http\Resources;

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

    public function withModeration(): self
    {
        $this->moderation = true;

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
        }

        return $data;
    }
}
