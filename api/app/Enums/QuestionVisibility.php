<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Komu je otázka určená — celej sále, alebo len organizátorovi.
 *
 * `Public` je pôvodné a stále prevažujúce správanie: otázka je napísaná preto,
 * aby ju videli aj ostatní, ide na plátno a zodpovedaná je aj FAQ obsahom
 * stránky.
 *
 * `Private` je druhý prípad, ktorý verejný nikdy nebude — otázka, ktorá sa na
 * plátno nehodí („som na vozíku, dostanem sa dnu?"), a **podnet z publika**
 * počas akcie („v sále je zima", „nie je počuť"). Nikde sa nezverejní a jediná
 * cesta, ako sa pisateľ dozvie odpoveď, je e-mail — preto je pri súkromnej
 * otázke oznámenie e-mailom povinné, nie ponuka.
 *
 * Prepnúť súkromnú otázku na verejnú sa **zámerne nedá ani organizátorovi**:
 * pisateľ ju písal s tým, že ju nikto iný neuvidí, a to je sľub, ktorý sa
 * jedným klikom v dashboarde nemá dať zrušiť.
 */
enum QuestionVisibility: string implements HasLabel
{
    use ProvidesOptions;

    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return __('questions.visibility.' . $this->value);
    }

    public function isPrivate(): bool
    {
        return $this === self::Private;
    }

    /**
     * Potrebuje otázka kontakt na pisateľa, aby dávala zmysel?
     *
     * Súkromnú odpoveď nemá kde uvidieť — nie je vo verejnom zozname ani na
     * stene. Bez adresy by to bola správa do prázdna.
     */
    public function requiresContact(): bool
    {
        return $this->isPrivate();
    }
}
