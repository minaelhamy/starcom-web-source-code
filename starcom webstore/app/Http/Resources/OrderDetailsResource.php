<?php

namespace App\Http\Resources;


use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $walletPaidAmount = (float)$this->wallet_paid_amount;
        $cashOnDeliveryAmount = (float)$this->cash_on_delivery_amount;
        $isSplitPayment = $walletPaidAmount > 0 && $cashOnDeliveryAmount > 0;

        return [
            'id'                             => $this->id,
            'order_serial_no'                => $this->order_serial_no,
            'user_id'                        => $this->user_id,
            "subtotal_currency_price"        => AppLibrary::currencyAmountFormat($this->subtotal),
            "tax_currency_price"             => AppLibrary::currencyAmountFormat($this->tax),
            "discount_currency_price"        => AppLibrary::currencyAmountFormat($this->discount),
            "total_currency_price"           => AppLibrary::currencyAmountFormat($this->total),
            "total_amount_price"             => AppLibrary::flatAmountFormat($this->total),
            "shipping_charge_currency_price" => AppLibrary::currencyAmountFormat($this->shipping_charge),
            'order_type'                     => $this->order_type,
            'order_date'                     => AppLibrary::date($this->order_datetime),
            'order_time'                     => AppLibrary::time($this->order_datetime),
            'order_datetime'                 => AppLibrary::datetime($this->order_datetime),
            'payment_method'                 => $this->payment_method,
            'payment_method_name'            => $isSplitPayment ? trans('all.label.pay_later_plus_cod') : $this->paymentMethod?->name,
            'payment_status'                 => $this->payment_status,
            'wallet_paid_amount'             => $walletPaidAmount,
            'wallet_paid_amount_price'       => AppLibrary::flatAmountFormat($walletPaidAmount),
            'wallet_paid_amount_currency'    => AppLibrary::currencyAmountFormat($walletPaidAmount),
            'cash_on_delivery_amount'        => $cashOnDeliveryAmount,
            'cash_on_delivery_amount_price'  => AppLibrary::flatAmountFormat($cashOnDeliveryAmount),
            'cash_on_delivery_amount_currency' => AppLibrary::currencyAmountFormat($cashOnDeliveryAmount),
            'is_split_payment'               => $isSplitPayment,
            'status'                         => $this->status,
            'reason'                         => $this->reason,
            'source'                         => $this->source,
            'active'                         => (int) $this->active,
            'return_and_refund'              => !$this->returnAndRefund,
            'user'                           => new UserResource($this->user),
            'order_address'                  => AddressResource::collection($this->address),
            'outlet_address'                 => new OutletResource($this?->outletAddress),
            'order_products'                 => OrderProductResource::collection($this->orderProducts),
            'pos_payment_method'             => $this->pos_payment_method,
            'pos_payment_method_name'        => trans("posPaymentMethod." . $this->pos_payment_method),
            'pos_payment_note'               => $this->pos_payment_note,
            "pos_received_amount"            => AppLibrary::flatAmountFormat($this->pos_received_amount),
            "pos_currency_received_amount"   => AppLibrary::currencyAmountFormat($this->pos_received_amount),
            "change_currency_amount"         => AppLibrary::currencyAmountFormat($this->pos_received_amount-$this->total),
        ];
    }
}
