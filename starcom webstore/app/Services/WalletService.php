<?php

namespace App\Services;

use App\Enums\CreditFacilityStatus;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use App\Models\CreditFacilityOrderAllocation;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getDebitedAmountForOrder(Order $order): float
    {
        $facilityAmount = (float)CreditFacilityOrderAllocation::where('order_id', $order->id)->sum('amount');
        $walletAdjustmentAmount = (float)WalletTransaction::where('order_id', $order->id)
            ->where('type', 'wallet_adjustment_purchase')
            ->sum('amount');

        return $facilityAmount + $walletAdjustmentAmount;
    }

    public function creditByFacility(User $user, CreditApplication $application, User $institution, float $amount, string $description, array $facilityData = []): CreditFacility
    {
        return DB::transaction(function () use ($user, $application, $institution, $amount, $description, $facilityData) {
            $facility = CreditFacility::create([
                'credit_application_id'         => $application->id,
                'user_id'                       => $user->id,
                'financial_institution_user_id' => $institution->id,
                'financial_institution_employee_user_id' => $facilityData['financial_institution_employee_user_id'] ?? $institution->id,
                'status'                        => CreditFacilityStatus::APPROVED,
                'approved_amount'               => $amount,
                'available_amount'              => $amount,
                'utilized_amount'               => 0,
                'duration_days'                 => $facilityData['duration_days'],
                'starts_at'                     => now(),
                'due_at'                        => now()->addDays($facilityData['duration_days']),
                'reviewed_at'                   => now(),
                'notes'                         => $facilityData['notes'] ?? null,
            ]);

            $before = (float)$user->balance;
            $user->balance = $before + $amount;
            $user->save();

            WalletTransaction::create([
                'user_id'                       => $user->id,
                'financial_institution_user_id' => $institution->id,
                'credit_application_id'         => $application->id,
                'credit_facility_id'            => $facility->id,
                'type'                          => 'facility_approved',
                'direction'                     => 'credit',
                'amount'                        => $amount,
                'balance_before'                => $before,
                'balance_after'                 => (float)$user->balance,
                'description'                   => $description,
            ]);

            return $facility;
        });
    }

    public function debitForOrder(Order $order): void
    {
        $userBalance = (float)User::findOrFail($order->user_id)->balance;
        if ($userBalance + 0.000001 < (float)$order->total) {
            throw new Exception(trans('all.message.insufficient_wallet_balance'), 422);
        }

        $this->debitUpToForOrder($order, (float)$order->total);
    }

    public function debitUpToForOrder(Order $order, ?float $requestedAmount = null): float
    {
        return DB::transaction(function () use ($order, $requestedAmount) {
            $user = User::lockForUpdate()->findOrFail($order->user_id);
            $orderTotal = (float)$order->total;
            $amountToDebit = min(
                (float)$user->balance,
                $requestedAmount ?? $orderTotal,
                $orderTotal
            );

            if ($amountToDebit <= 0) {
                throw new Exception(trans('all.message.insufficient_wallet_balance'), 422);
            }

            $remaining = $amountToDebit;
            $runningBalance = (float)$user->balance;

            $facilities = CreditFacility::where('user_id', $user->id)
                ->where('status', CreditFacilityStatus::APPROVED)
                ->where('available_amount', '>', 0)
                ->orderBy('due_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($facilities as $facility) {
                if ($remaining <= 0) {
                    break;
                }

                $allocationAmount = min($remaining, (float)$facility->available_amount);
                if ($allocationAmount <= 0) {
                    continue;
                }

                $facility->available_amount = (float)$facility->available_amount - $allocationAmount;
                $facility->utilized_amount = (float)$facility->utilized_amount + $allocationAmount;
                $facility->save();

                CreditFacilityOrderAllocation::create([
                    'credit_facility_id' => $facility->id,
                    'order_id'           => $order->id,
                    'amount'             => $allocationAmount,
                ]);

                WalletTransaction::create([
                    'user_id'                       => $user->id,
                    'financial_institution_user_id' => $facility->financial_institution_user_id,
                    'credit_application_id'         => $facility->credit_application_id,
                    'credit_facility_id'            => $facility->id,
                    'order_id'                      => $order->id,
                    'type'                          => 'pay_later_purchase',
                    'direction'                     => 'debit',
                    'amount'                        => $allocationAmount,
                    'balance_before'                => $runningBalance,
                    'balance_after'                 => $runningBalance - $allocationAmount,
                    'description'                   => 'Pay later order #' . $order->order_serial_no,
                ]);

                $runningBalance -= $allocationAmount;
                $remaining -= $allocationAmount;
            }

            if ($remaining > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'wallet_adjustment_purchase',
                    'direction'      => 'debit',
                    'amount'         => $remaining,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance - $remaining,
                    'description'    => 'Wallet purchase #' . $order->order_serial_no,
                ]);

                $runningBalance -= $remaining;
            }

            $user->balance = $runningBalance;
            $user->save();

            return (float)$amountToDebit;
        });
    }

    public function refundOrder(Order $order, string $description): void
    {
        DB::transaction(function () use ($order, $description) {
            $user = User::lockForUpdate()->findOrFail($order->user_id);
            $allocations = CreditFacilityOrderAllocation::with('facility')
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            $refunded = 0;
            $runningBalance = (float)$user->balance;

            foreach ($allocations as $allocation) {
                $facility = $allocation->facility;
                if ($facility) {
                    $facility->available_amount = (float)$facility->available_amount + (float)$allocation->amount;
                    $facility->utilized_amount = max(0, (float)$facility->utilized_amount - (float)$allocation->amount);
                    $facility->save();
                }

                WalletTransaction::create([
                    'user_id'                       => $user->id,
                    'financial_institution_user_id' => $facility?->financial_institution_user_id,
                    'credit_application_id'         => $facility?->credit_application_id,
                    'credit_facility_id'            => $facility?->id,
                    'order_id'                      => $order->id,
                    'type'                          => 'pay_later_refund',
                    'direction'                     => 'credit',
                    'amount'                        => $allocation->amount,
                    'balance_before'                => $runningBalance,
                    'balance_after'                 => $runningBalance + (float)$allocation->amount,
                    'description'                   => $description,
                ]);

                $runningBalance += (float)$allocation->amount;
                $refunded += (float)$allocation->amount;
            }

            $walletAdjustmentPurchaseAmount = (float)WalletTransaction::where('order_id', $order->id)
                ->where('type', 'wallet_adjustment_purchase')
                ->sum('amount');

            if ($walletAdjustmentPurchaseAmount > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'wallet_refund',
                    'direction'      => 'credit',
                    'amount'         => $walletAdjustmentPurchaseAmount,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance + $walletAdjustmentPurchaseAmount,
                    'description'    => $description,
                ]);
                $runningBalance += $walletAdjustmentPurchaseAmount;
                $refunded += $walletAdjustmentPurchaseAmount;
            }

            if ($refunded > 0) {
                $user->balance = $runningBalance;
                $user->save();
            }
        });
    }
}
