<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Libraries\AppLibrary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

        $timeline = self::timelineForUser($user);

        if ($invoicesCount === 0 || $totalInvoiceAmount <= 0) {
            return self::empty('لا توجد بيانات مشتريات كافية لحساب Starcom Intelligence حتى الآن.');
        }

        $averageDailySales = $totalInvoiceAmount / $invoicesCount;
        $averageWeeklyPurchase = $averageDailySales * 0.9;
        $averageMonthlySales = $averageDailySales * 26;
        $totalMonthlyPurchase = $averageMonthlySales * 0.95;
        $creditProposedAmount = $totalMonthlyPurchase * 0.125;

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
            'first_invoice_date'               => $timeline['first_invoice_date'],
            'first_invoice_date_label'         => $timeline['first_invoice_date_label'],
            'monthly_purchases'                => $timeline['monthly_purchases'],
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
        $creditProposedAmount = $totalMonthlyPurchase * 0.125;

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
            'first_invoice_date'               => null,
            'first_invoice_date_label'         => '--',
            'monthly_purchases'                => [],
            'label'                            => 'Starcom Intelligence',
            'note'                             => $note,
        ];
    }

    private static function timelineForUser(User $user): array
    {
        $intelligenceInvoices = self::intelligenceInvoices($user);
        $systemInvoices = self::systemInvoices($user);

        $monthlyPurchases = $intelligenceInvoices
            ->merge($systemInvoices)
            ->filter(fn (array $invoice) => !blank($invoice['invoice_date']))
            ->sortBy('invoice_date')
            ->groupBy(fn (array $invoice) => Carbon::parse($invoice['invoice_date'])->format('Y-m'))
            ->map(function (Collection $group, string $monthKey) {
                $amount = (float)$group->sum('invoice_amount');
                $monthDate = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth()->locale('ar');

                return [
                    'month_key'        => $monthKey,
                    'month_label'      => $monthDate->translatedFormat('F Y'),
                    'invoice_count'    => $group->count(),
                    'amount'           => $amount,
                    'amount_currency'  => AppLibrary::currencyAmountFormat($amount),
                ];
            })
            ->values();

        $firstInvoiceDate = $monthlyPurchases->isNotEmpty()
            ? Carbon::parse($monthlyPurchases->first()['month_key'] . '-01')->startOfMonth()
            : null;

        $firstExactInvoice = $intelligenceInvoices
            ->merge($systemInvoices)
            ->filter(fn (array $invoice) => !blank($invoice['invoice_date']))
            ->sortBy('invoice_date')
            ->first();

        if ($firstExactInvoice) {
            $firstInvoiceDate = Carbon::parse($firstExactInvoice['invoice_date']);
        }

        if ($monthlyPurchases->isNotEmpty()) {
            $filledMonthlyPurchases = collect();
            $cursor = Carbon::createFromFormat('Y-m', $monthlyPurchases->first()['month_key'])->startOfMonth();
            $lastMonth = Carbon::createFromFormat('Y-m', $monthlyPurchases->last()['month_key'])->startOfMonth();
            $monthlyMap = $monthlyPurchases->keyBy('month_key');

            while ($cursor->lessThanOrEqualTo($lastMonth)) {
                $monthKey = $cursor->format('Y-m');
                if ($monthlyMap->has($monthKey)) {
                    $filledMonthlyPurchases->push($monthlyMap->get($monthKey));
                } else {
                    $filledMonthlyPurchases->push([
                        'month_key'       => $monthKey,
                        'month_label'     => $cursor->copy()->locale('ar')->translatedFormat('F Y'),
                        'invoice_count'   => 0,
                        'amount'          => 0,
                        'amount_currency' => AppLibrary::currencyAmountFormat(0),
                    ]);
                }

                $cursor->addMonth();
            }

            $monthlyPurchases = $filledMonthlyPurchases;
        }

        return [
            'first_invoice_date'       => $firstInvoiceDate?->toDateString(),
            'first_invoice_date_label' => $firstInvoiceDate ? AppLibrary::date($firstInvoiceDate) : '--',
            'monthly_purchases'        => $monthlyPurchases->all(),
        ];
    }

    private static function intelligenceInvoices(User $user): Collection
    {
        $query = DB::table('starcom_intelligence')
            ->select(['invoice_date', 'invoice_amount'])
            ->where('phone', $user->phone);

        if (!blank($user->country_code)) {
            $query->where(function ($builder) use ($user) {
                $builder->whereNull('country_code')
                    ->orWhere('country_code', '')
                    ->orWhere('country_code', $user->country_code);
            });
        }

        return $query
            ->whereNotNull('invoice_date')
            ->get()
            ->map(fn ($row) => [
                'invoice_date'   => $row->invoice_date,
                'invoice_amount' => (float)$row->invoice_amount,
            ]);
    }

    private static function systemInvoices(User $user): Collection
    {
        return DB::table('orders')
            ->selectRaw('COALESCE(order_datetime, created_at) as invoice_date, total as invoice_amount')
            ->where('user_id', $user->id)
            ->whereNotIn('status', [OrderStatus::CANCELED, OrderStatus::REJECTED])
            ->get()
            ->map(fn ($row) => [
                'invoice_date'   => $row->invoice_date,
                'invoice_amount' => (float)$row->invoice_amount,
            ]);
    }
}
