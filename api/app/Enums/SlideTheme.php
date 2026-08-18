<?php

namespace App\Enums;

/**
 * Motív generovanej snímky.
 *
 * Farby sú tu, nie v konfiguračnom súbore: sú súčasťou vzhľadu, nie
 * nastavenia prevádzky, a organizátor si vyberá z troch hotových motívov,
 * nie z palety.
 */
enum SlideTheme: string
{
    /** Tmavý — fotka kanála rozostretá na pozadí. Predvolený, na plátne najlepší. */
    case Dark = 'dark';

    /** Svetlý — na projektor v presvetlenej sále a na tlač. */
    case Light = 'light';

    /** Farebný prechod — keď kanál fotku nemá alebo sa nehodí. */
    case Bold = 'bold';

    /**
     * @return array{
     *     bg_from: string, bg_to: string, bg_from2: string, bg_to2: string,
     *     text: string, muted: string, accent: string,
     *     card: string, card_text: string, scrim: int
     * }
     */
    public function palette(): array
    {
        return match ($this) {
            self::Dark => [
                'bg_from' => '#0B1220', 'bg_to' => '#16233F',
                'bg_from2' => '#0B1220', 'bg_to2' => '#16233F',
                'text' => '#FFFFFF', 'muted' => '#9FB3D1', 'accent' => '#7DB3FF',
                'card' => '#FFFFFF', 'card_text' => '#0B1220',
                // Sila stmavenia fotky na pozadí (0 = nič, 127 = neviditeľná).
                // Text musí byť čitateľný aj cez svetlú fotku zo sály.
                'scrim' => 88,
            ],
            self::Light => [
                'bg_from' => '#FFFFFF', 'bg_to' => '#E7EDF6',
                'bg_from2' => '#FFFFFF', 'bg_to2' => '#E7EDF6',
                'text' => '#0B1220', 'muted' => '#4A5C77', 'accent' => '#1D4ED8',
                'card' => '#FFFFFF', 'card_text' => '#0B1220',
                'scrim' => 0,
            ],
            self::Bold => [
                'bg_from' => '#2563EB', 'bg_to' => '#7C3AED',
                'bg_from2' => '#0EA5E9', 'bg_to2' => '#9333EA',
                'text' => '#FFFFFF', 'muted' => '#DCE7FF', 'accent' => '#FFFFFF',
                'card' => '#FFFFFF', 'card_text' => '#0B1220',
                'scrim' => 0,
            ],
        };
    }

    /** Kreslí sa fotka kanála rozostretá cez celé pozadie? */
    public function usesPhotoBackdrop(): bool
    {
        return $this === self::Dark;
    }
}
