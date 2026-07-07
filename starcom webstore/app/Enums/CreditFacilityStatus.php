<?php

namespace App\Enums;

interface CreditFacilityStatus
{
    const PENDING_APPROVAL = 'pending_approval';
    const APPROVED         = 'approved';
    const DECLINED         = 'declined';
    const EXPIRED          = 'expired';
    const SETTLED          = 'settled';
}
