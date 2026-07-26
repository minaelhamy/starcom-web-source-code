<?php

namespace App\Support;

use App\Libraries\AppLibrary;
use App\Models\User;

class StarcomIntelligenceCalculator
{
    private const MIN_AVERAGE_MONTHLY_PURCHASE = 54000;
    private const MAX_AVERAGE_MONTHLY_PURCHASE = 110000;

    public static function forUser(?User $user): array
    {
        $averageMonthlyPurchase = self::averageMonthlyPurchaseForUser($user);

        return [
            'average_monthly_purchase_last_12_months' => $averageMonthlyPurchase,
            'average_monthly_purchase_last_12_months_currency' => AppLibrary::currencyAmountFormat($averageMonthlyPurchase),
            'label' => 'Starcom Intelligence',
            'note'  => 'متوسط تقديري لمشتريات العميل الشهرية من ستاركوم خلال آخر ١٢ شهر.',
        ];
    }

    private static function averageMonthlyPurchaseForUser(?User $user): int
    {
        if ($user && !is_null($user->estimated_average_monthly_purchase)) {
            return (int) round((float) $user->estimated_average_monthly_purchase);
        }

        $seed = $user?->phone ?: ($user?->id ? (string)$user->id : 'starcom-default');
        $hash = abs(crc32($seed));
        $range = self::MAX_AVERAGE_MONTHLY_PURCHASE - self::MIN_AVERAGE_MONTHLY_PURCHASE;

        return self::MIN_AVERAGE_MONTHLY_PURCHASE + ($hash % ($range + 1));
    }
}
