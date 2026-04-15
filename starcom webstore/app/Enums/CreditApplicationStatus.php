<?php

namespace App\Enums;

interface CreditApplicationStatus
{
    const PENDING  = 'pending';
    const APPROVED = 'approved';
    const DECLINED = 'declined';
}
