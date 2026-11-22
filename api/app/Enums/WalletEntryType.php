<?php

namespace App\Enums;

/**
 * Ledger entry kinds. Top-up adds money, charge pays for a booking, refund gives back unplayed plays.
 * Hold and release are from the old hold-then-charge model and only appear in older history.
 */
enum WalletEntryType: string
{
    case TopUp = 'topup';
    case Charge = 'charge';
    case Refund = 'refund';
    case Hold = 'hold';
    case Release = 'release';
}
