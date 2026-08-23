<?php

namespace App\Http\Resources;

use App\Enums\QuestionStatus;
use App\Support\BoardToken;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nástenka pre dashboard organizátora.
 *
 * Na rozdiel od verejnej odpovede tu token JE — organizátor potrebuje odkaz,
 * kód aj adresy na stiahnutie snímky. Verejná stránka token nikdy neposiela
 * (`QuestionBoard::$hidden`), lebo tam by bol len na to, aby unikol.
 */
class QuestionBoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType(),
            'target_id' => (int) $this->boardable_id,
            'title' => $this->title(),

            'token' => $this->token,
            'code' => BoardToken::forDisplay($this->token),
            // Adresy snímky sa neposielajú — front si ich skladá z tokenu nad
            // svojím `/api` základom, rovnako ako QR kód vstupenky. Absolútna
            // adresa z `route()` by v SPA obišla proxy a bola by to druhá
            // definícia tej istej cesty.
            'public_url' => PublicUrl::questionBoard($this->token),
            'wall_url' => PublicUrl::questionWall($this->token),

            'is_open' => (bool) $this->is_open,
            'accepts_questions' => $this->acceptsQuestions(),
            'moderation' => (bool) $this->moderation,
            'show_questions' => (bool) $this->show_questions,
            'allow_upvotes' => (bool) $this->allow_upvotes,
            'ask_for_name' => (bool) $this->ask_for_name,
            'allow_private' => (bool) $this->allow_private,
            'intro' => $this->intro,

            'questions_count' => (int) $this->questions_count,
            // Odznak „čaká na teba" pri zapnutom moderovaní. Jeden COUNT na
            // nástenku; nástienok je na podujatie rádovo jednotky.
            'pending_count' => $this->questions()->where('status', QuestionStatus::Pending->value)->count(),
            // Odznak „ozvi sa im": súkromný vstup nikde inde vidieť nie je
            // a pisateľ čaká odpoveď e-mailom.
            'private_open_count' => $this->questions()->onlyPrivate()->whereNull('answered_at')->count(),
        ];
    }
}
