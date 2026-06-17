<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Libraries\AppLibrary;

class ReturnOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'order_id'             => $this->order_id,
            'order_serial_no'      => $this->order_serial_no,
            'date'                 => $this->date,
            'converted_date'       => AppLibrary::datetime($this->date),
            'reference_no'         => $this->reference_no,
            'total'                => $this->total,
            'refund_amount'        => $this->refund_amount,
            'refund_amount_currency' => AppLibrary::currencyAmountFormat($this->refund_amount),
            'total_currency_price' => AppLibrary::currencyAmountFormat($this->total),
            'total_float_price'    => AppLibrary::flatAmountFormat($this->total),
            'reason'               => $this->reason,
            'user'                 => $this->user,
            'order'                => $this->order,
        ];
    }
}
