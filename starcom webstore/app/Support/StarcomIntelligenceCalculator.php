<?php

namespace App\Support;

use App\Libraries\AppLibrary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StarcomIntelligenceCalculator
{
    public static function forUser(?User $user): array
    {
        if (!$user || blank($user->phone)) {
            return self::empty('لا توجد بيانات مشتريات كافية لحساب Starcom Intelligence حتى الآن.');
        }

        $query = DB::table('starcom_intelligence')
            ->where('phone', $user->phone);

        if (!blank($user->country_code)) {
            $query->where(function ($builder) use ($user) {
                $builder->whereNull('country_code')
                    ->orWhere('country_code', '')
                    ->orWhere('country_code', $user->country_code);
            });
        }

        $metrics = $query->selectRaw('COUNT(*) as invoices_count, COALESCE(SUM(invoice_amount), 0) as total_invoice_amount')
            ->first();

        $invoicesCount = (int)($metrics->invoices_count ?? 0);
        $totalInvoiceAmount = (float)($metrics->total_invoice_amount ?? 0);

        if ($invoicesCount === 0 || $totalInvoiceAmount <= 0) {
            return self::empty('لا توجد بيانات مشتريات كافية لحساب Starcom Intelligence حتى الآن.');
        }

        $averageDailySales = $totalInvoiceAmount / $invoicesCount;
        $averageWeeklyPurchase = $averageDailySales * 0.9;
        $averageMonthlySales = $averageDailySales * 26;
        $totalMonthlyPurchase = $averageMonthlySales * 0.95;
        $creditProposedAmount = $totalMonthlyPurchase * 0.25;

        return [
            'is_placeholder'                   => false,
            'invoices_count'                   => $invoicesCount,
            'total_invoice_amount'             => $totalInvoiceAmount,
            'total_invoice_amount_currency'    => AppLibrary::currencyAmountFormat($totalInvoiceAmount),
            'average_weekly_purchase'          => $averageWeeklyPurchase,
            'average_weekly_purchase_currency' => AppLibrary::currencyAmountFormat($averageWeeklyPurchase),
            'average_daily_sales'              => $averageDailySales,
            'average_daily_sales_currency'     => AppLibrary::currencyAmountFormat($averageDailySales),
            'average_monthly_sales'            => $averageMonthlySales,
            'average_monthly_sales_currency'   => AppLibrary::currencyAmountFormat($averageMonthlySales),
            'total_monthly_purchase'           => $totalMonthlyPurchase,
            'total_monthly_purchase_currency'  => AppLibrary::currencyAmountFormat($totalMonthlyPurchase),
            'credit_proposed_amount'           => $creditProposedAmount,
            'credit_proposed_amount_currency'  => AppLibrary::currencyAmountFormat($creditProposedAmount),
            'label'                            => 'Starcom Intelligence',
            'note'                             => 'تم احتساب المؤشرات اعتماداً على تاريخ مشتريات العميل من ستاركوم.',
        ];
    }

    private static function empty(string $note): array
    {
        $averageDailySales = 15000;
        $averageWeeklyPurchase = $averageDailySales * 0.9;
        $averageMonthlySales = $averageDailySales * 26;
        $totalMonthlyPurchase = $averageMonthlySales * 0.95;
        $creditProposedAmount = $totalMonthlyPurchase * 0.25;

        return [
            'is_placeholder'                   => true,
            'invoices_count'                   => 0,
            'total_invoice_amount'             => 0,
            'total_invoice_amount_currency'    => AppLibrary::currencyAmountFormat(0),
            'average_weekly_purchase'          => $averageWeeklyPurchase,
            'average_weekly_purchase_currency' => AppLibrary::currencyAmountFormat($averageWeeklyPurchase),
            'average_daily_sales'              => $averageDailySales,
            'average_daily_sales_currency'     => AppLibrary::currencyAmountFormat($averageDailySales),
            'average_monthly_sales'            => $averageMonthlySales,
            'average_monthly_sales_currency'   => AppLibrary::currencyAmountFormat($averageMonthlySales),
            'total_monthly_purchase'           => $totalMonthlyPurchase,
            'total_monthly_purchase_currency'  => AppLibrary::currencyAmountFormat($totalMonthlyPurchase),
            'credit_proposed_amount'           => $creditProposedAmount,
            'credit_proposed_amount_currency'  => AppLibrary::currencyAmountFormat($creditProposedAmount),
            'label'                            => 'Starcom Intelligence',
            'note'                             => $note,
        ];
    }
}
