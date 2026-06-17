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

    public function refundOrderAmounts(
        Order $order,
        float $facilityRefundAmount,
        float $directWalletRefundAmount,
        float $cashRefundAmount,
        string $description,
        array $meta = []
    ): array {
        return DB::transaction(function () use (
            $order,
            $facilityRefundAmount,
            $directWalletRefundAmount,
            $cashRefundAmount,
            $description,
            $meta
        ) {
            $user = User::lockForUpdate()->findOrFail($order->user_id);
            $runningBalance = (float) $user->balance;
            $remainingFacilityRefund = round($facilityRefundAmount, 6);
            $facilityRefunds = [];

            if ($remainingFacilityRefund > 0) {
                $allocations = CreditFacilityOrderAllocation::with('facility')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($allocations as $allocation) {
                    if ($remainingFacilityRefund <= 0) {
                        break;
                    }

                    $refundAmount = min($remainingFacilityRefund, (float) $allocation->amount);
                    if ($refundAmount <= 0) {
                        continue;
                    }

                    $facility = $allocation->facility;
                    if ($facility) {
                        $facility->available_amount = (float) $facility->available_amount + $refundAmount;
                        $facility->utilized_amount = max(0, (float) $facility->utilized_amount - $refundAmount);
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
                        'amount'                        => $refundAmount,
                        'balance_before'                => $runningBalance,
                        'balance_after'                 => $runningBalance + $refundAmount,
                        'description'                   => $description,
                        'meta'                          => $meta,
                    ]);

                    $runningBalance += $refundAmount;
                    $remainingFacilityRefund -= $refundAmount;

                    $facilityRefunds[] = [
                        'credit_facility_id'            => $facility?->id,
                        'financial_institution_user_id' => $facility?->financial_institution_user_id,
                        'credit_application_id'         => $facility?->credit_application_id,
                        'amount'                        => $refundAmount,
                    ];

                    $allocation->amount = (float) $allocation->amount - $refundAmount;
                    if ((float) $allocation->amount <= 0.000001) {
                        $allocation->delete();
                    } else {
                        $allocation->save();
                    }
                }
            }

            $directWalletRefundAmount = round($directWalletRefundAmount, 6);
            if ($directWalletRefundAmount > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'wallet_purchase_refund',
                    'direction'      => 'credit',
                    'amount'         => $directWalletRefundAmount,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance + $directWalletRefundAmount,
                    'description'    => $description,
                    'meta'           => $meta,
                ]);

                $runningBalance += $directWalletRefundAmount;
            }

            $cashRefundAmount = round($cashRefundAmount, 6);
            if ($cashRefundAmount > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'cash_payment_wallet_refund',
                    'direction'      => 'credit',
                    'amount'         => $cashRefundAmount,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance + $cashRefundAmount,
                    'description'    => $description,
                    'meta'           => $meta,
                ]);

                $runningBalance += $cashRefundAmount;
            }

            if ($facilityRefundAmount > 0 || $directWalletRefundAmount > 0 || $cashRefundAmount > 0) {
                $user->balance = $runningBalance;
                $user->save();
            }

            return [
                'facility_refunds'             => $facilityRefunds,
                'direct_wallet_refund_amount'  => $directWalletRefundAmount,
                'cash_wallet_refund_amount'    => $cashRefundAmount,
            ];
        });
    }

    public function reverseReturnRefund(Order $order, array $refundMeta, string $description, array $meta = []): void
    {
        DB::transaction(function () use ($order, $refundMeta, $description, $meta) {
            $totalReversalAmount = round(
                collect($refundMeta['facility_refunds'] ?? [])->sum('amount')
                + (float) ($refundMeta['direct_wallet_refund_amount'] ?? 0)
                + (float) ($refundMeta['cash_wallet_refund_amount'] ?? 0),
                6
            );

            if ($totalReversalAmount <= 0) {
                return;
            }

            $user = User::lockForUpdate()->findOrFail($order->user_id);
            $runningBalance = (float) $user->balance;

            if ($runningBalance + 0.000001 < $totalReversalAmount) {
                throw new Exception('لا يمكن تعديل أو حذف المرتجع لأن رصيد المحفظة الحالي لا يكفي لعكس مبلغ الاسترداد.');
            }

            foreach ($refundMeta['facility_refunds'] ?? [] as $facilityRefund) {
                $amount = round((float) ($facilityRefund['amount'] ?? 0), 6);
                if ($amount <= 0) {
                    continue;
                }

                $facility = CreditFacility::lockForUpdate()->find($facilityRefund['credit_facility_id'] ?? null);
                if ($facility) {
                    if ((float) $facility->available_amount + 0.000001 < $amount) {
                        throw new Exception('لا يمكن عكس استرداد التمويل لأن الرصيد المتاح في المحفظة الممولة لم يعد كافياً.');
                    }

                    $facility->available_amount = (float) $facility->available_amount - $amount;
                    $facility->utilized_amount = (float) $facility->utilized_amount + $amount;
                    $facility->save();

                    CreditFacilityOrderAllocation::create([
                        'credit_facility_id' => $facility->id,
                        'order_id'           => $order->id,
                        'amount'             => $amount,
                    ]);
                }

                WalletTransaction::create([
                    'user_id'                       => $user->id,
                    'financial_institution_user_id' => $facilityRefund['financial_institution_user_id'] ?? null,
                    'credit_application_id'         => $facilityRefund['credit_application_id'] ?? null,
                    'credit_facility_id'            => $facilityRefund['credit_facility_id'] ?? null,
                    'order_id'                      => $order->id,
                    'type'                          => 'pay_later_return_reversal',
                    'direction'                     => 'debit',
                    'amount'                        => $amount,
                    'balance_before'                => $runningBalance,
                    'balance_after'                 => $runningBalance - $amount,
                    'description'                   => $description,
                    'meta'                          => $meta,
                ]);

                $runningBalance -= $amount;
            }

            $directWalletRefundAmount = round((float) ($refundMeta['direct_wallet_refund_amount'] ?? 0), 6);
            if ($directWalletRefundAmount > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'wallet_purchase_refund_reversal',
                    'direction'      => 'debit',
                    'amount'         => $directWalletRefundAmount,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance - $directWalletRefundAmount,
                    'description'    => $description,
                    'meta'           => $meta,
                ]);

                $runningBalance -= $directWalletRefundAmount;
            }

            $cashRefundAmount = round((float) ($refundMeta['cash_wallet_refund_amount'] ?? 0), 6);
            if ($cashRefundAmount > 0) {
                WalletTransaction::create([
                    'user_id'        => $user->id,
                    'order_id'       => $order->id,
                    'type'           => 'cash_payment_wallet_refund_reversal',
                    'direction'      => 'debit',
                    'amount'         => $cashRefundAmount,
                    'balance_before' => $runningBalance,
                    'balance_after'  => $runningBalance - $cashRefundAmount,
                    'description'    => $description,
                    'meta'           => $meta,
                ]);

                $runningBalance -= $cashRefundAmount;
            }

            $user->balance = $runningBalance;
            $user->save();
        });
    }
}
