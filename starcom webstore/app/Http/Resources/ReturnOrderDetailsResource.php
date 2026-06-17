<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Libraries\AppLibrary;

class ReturnOrderDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'order_id'          => $this->order_id,
            'order_serial_no'   => $this->order_serial_no,
            'user_name'         => $this->user->name,
            'date'              => $this->date,
            'converted_date'    => AppLibrary::datetime($this->date),
            'reference_no'      => $this->reference_no,
            'tax'               => $this->tax,
            'discount'          => $this->discount,
            'subtotal'          => $this->subtotal,
            'total'             => $this->total,
            'refund_amount'     => $this->refund_amount,
            'tax_currency'      => AppLibrary::currencyAmountFormat($this->tax),
            'discount_currency' => AppLibrary::currencyAmountFormat($this->discount),
            'subtotal_currency' => AppLibrary::currencyAmountFormat($this->subtotal),
            'total_currency'    => AppLibrary::currencyAmountFormat($this->total),
            'refund_amount_currency' => AppLibrary::currencyAmountFormat($this->refund_amount),
            'reason'            => $this->reason,
            'user'              => $this->user,
            'order'             => $this->order,
            'creator'           => $this->creator,
            'created_at'        => AppLibrary::datetime($this->created_at),
            'file'              => $this->file,
            'products'          => ProductWithVariationResource::collection($this->stocks)
        ];
    }
}
