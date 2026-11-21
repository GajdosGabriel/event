<?php

namespace App\Enums;

/**
 * Odkiaľ návštevník prišiel na detail podujatia — parameter `?z=` v odkazoch,
 * ktoré organizátor rozosiela z panela Propagácia.
 *
 * Hodnoty sú v adrese, ktorú ľudia vidia a niekedy prepisujú, preto krátke
 * slovenské slová. Neznáma hodnota sa ticho ignoruje — štatistika je doplnok
 * a cudzí parameter nesmie nič zhodiť.
 */
enum ViewSource: string
{
    /** Samotný QR kód (PNG/SVG) z panela — leták, nástenka, vlastná grafika. */
    case Qr = 'qr';

    /** QR na vygenerovanej pozvánke (štvorec, story, plátno, plagát). */
    case Invite = 'pozvanka';

    /** Odkaz alebo text pozvánky poslaný z panela (správa, e-mail, sieť). */
    case Link = 'odkaz';
}
