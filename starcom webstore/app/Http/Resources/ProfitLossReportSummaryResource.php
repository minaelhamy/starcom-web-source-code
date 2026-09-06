<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitLossReportSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_products' => $this['total_products'],
            'total_quantity' => $this['total_quantity'],
            'total_cost' => $this['total_cost'],
            'total_sales' => $this['total_sales'],
            'gross_profit' => $this['gross_profit'],
            'total_cost_currency' => AppLibrary::currencyAmountFormat($this['total_cost']),
            'total_sales_currency' => AppLibrary::currencyAmountFormat($this['total_sales']),
            'gross_profit_currency' => AppLibrary::currencyAmountFormat($this['gross_profit']),
        ];
    }
}
