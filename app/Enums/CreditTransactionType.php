<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    /** Credits bought by the user. */
    case Purchase = 'purchase';

    /** What a completed generation actually cost them. */
    case Charge = 'charge';
}
