<?php

namespace App\Enums;

enum PromoClaimStatus: string
{
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
