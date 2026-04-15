<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'type'               => $this->type,
            'direction'          => $this->direction,
            'amount'             => (float)$this->amount,
            'amount_currency'    => AppLibrary::currencyAmountFormat($this->amount),
            'balance_before'     => (float)$this->balance_before,
            'balance_after'      => (float)$this->balance_after,
            'description'        => $this->description,
            'created_at'         => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'order_serial_no'    => $this->order?->order_serial_no,
            'institution_name'   => $this->institution?->financialInstitutionProfile?->company_name,
        ];
    }
}
