<?php

namespace App\Enums;

/** Ledger entry kinds. Hold/release move money between available and reserved; charge spends it. */
enum WalletEntryType: string
{
    case TopUp = 'topup';
    case Hold = 'hold';
    case Charge = 'charge';
    case Release = 'release';
}
