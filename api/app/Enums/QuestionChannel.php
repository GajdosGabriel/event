<?php

namespace App\Enums;

/**
 * Ktorou cestou otázka prichádza k nástenke.
 *
 * Nástenka je jedna, ale vedú k nej dva vchody a nemajú rovnaké pravidlá:
 *
 * - **Wall** je adresa z QR premietnutého v sále. Tá má byť živá len okolo
 *   podujatia — kým sa skúša technika, na plátne ešte nemá visieť fungujúci
 *   formulár, a týždeň dopredu už vôbec nie. Preto pre ňu platí `opens_at`.
 * - **EventPage** je verejný detail podujatia. Tam sa človek pýta z gauča
 *   („je tam parkovanie?", „môžem prísť s deťmi?") a otázka má zmysel presne
 *   vtedy, keď sa o podujatí dozvie — teda kedykoľvek pred ním. Čakať na
 *   `opens_at` by znamenalo, že tento vchod je otvorený len tie isté hodiny
 *   ako QR, čiže nikdy vtedy, keď je potrebný.
 *
 * `is_open` a `closes_at` platia pre oba vchody rovnako — ručný vypínač
 * organizátora je ručný vypínač a mesiac po akcii sa už nikto nepýta nič,
 * na čo by niekto odpovedal.
 */
enum QuestionChannel
{
    /** Nástenka z QR kódu a plátno v sále (/q/{token}). */
    case Wall;

    /** Verejný detail podujatia (/podujatia/{slug}). */
    case EventPage;

    /** Platí pre tento vchod začiatok okna, alebo stačí zapnutá nástenka? */
    public function respectsOpensAt(): bool
    {
        return $this === self::Wall;
    }
}
