<?php

namespace App\Support;

/**
 * Maskovaný tvar e-mailu pre front: „gabo@gmail.com“ → „g•••o@gmail.com“.
 *
 * Celú adresu cudzieho používateľa vidí len admin a on sám (viď UserResource);
 * všade inde ide von len tento tvar — stačí na rozlíšenie účtov, ale adresa
 * sa z neho zrekonštruovať nedá. Doména ostáva, podľa nej sa účty spoznajú.
 */
class EmailMask
{
    private const DOTS = '•••';

    public static function mask(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return self::DOTS;
        }

        $local = mb_substr($email, 0, $at);
        $domain = mb_substr($email, $at + 1);
        $length = mb_strlen($local);

        // Pri krátkom mene by prvý + posledný znak prezradil celé meno.
        $masked = match (true) {
            $length === 0 => self::DOTS,
            $length < 3 => mb_substr($local, 0, 1) . self::DOTS,
            default => mb_substr($local, 0, 1) . self::DOTS . mb_substr($local, -1),
        };

        return $masked . '@' . $domain;
    }
}
