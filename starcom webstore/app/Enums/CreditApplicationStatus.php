<?php

namespace App\Enums;

interface CreditApplicationStatus
{
    const PENDING          = 'pending';
    const PENDING_APPROVAL = 'pending_approval';
    const APPROVED         = 'approved';
    const DECLINED         = 'declined';
}
