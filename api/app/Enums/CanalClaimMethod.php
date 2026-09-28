<?php

namespace App\Enums;

/**
 * Ako žiadateľ preukázal, že kanál smie prevziať (viď CanalClaims).
 */
enum CanalClaimMethod: string
{
    /** Pozvánka systému na kontaktnú adresu (prihláška, oslovenie po akcii). */
    case Invitation = 'invitation';

    /** Žiadosť z verejnej stránky, potvrdená odkazom z kontaktnej schránky kanála. */
    case ContactEmail = 'contact_email';

    /** Žiadosť z verejnej stránky, ktorú posúdi administrátor. */
    case AdminReview = 'admin_review';
}
