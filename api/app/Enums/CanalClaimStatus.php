<?php

namespace App\Enums;

/**
 * Stav žiadosti o prevzatie kanála.
 *
 *   pending ──confirm/approve──▶ completed ──contest──▶ contested ──revert──▶ reverted
 *      └──reject──▶ rejected        (contested sa dá aj uzavrieť ako completed)
 *      └──(po platnosti)──▶ expired
 */
enum CanalClaimStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Contested = 'contested';
    case Reverted = 'reverted';
    case Expired = 'expired';
}
