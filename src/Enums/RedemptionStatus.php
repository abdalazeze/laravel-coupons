<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Enums;

enum RedemptionStatus: string
{
    case Applied = 'applied';
    case Reversed = 'reversed';
}
