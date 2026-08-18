<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stav otázky z publika.
 *
 * `Pending` vzniká len na nástenkach so zapnutým moderovaním. Bez neho ide
 * otázka rovno do `Published` — inak by sa organizátor musel pri každej otázke
 * prepnúť do dashboardu a celý zmysel „opýtaj sa za tri sekundy" by padol.
 *
 * `Hidden` je návrat, nie mazanie: skrytá otázka zostáva v moderačnom zozname,
 * aby sa dala vrátiť späť a aby bolo vidieť, koľko toho spamer poslal.
 * „Zodpovedané" stav nie je — to je `answered_at`, lebo otázka môže byť
 * zodpovedaná a zároveň zverejnená.
 */
enum QuestionStatus: string implements HasLabel
{
    use ProvidesOptions;

    case Pending   = 'pending';
    case Published = 'published';
    case Hidden    = 'hidden';

    public function label(): string
    {
        return __('questions.status.' . $this->value);
    }
}
