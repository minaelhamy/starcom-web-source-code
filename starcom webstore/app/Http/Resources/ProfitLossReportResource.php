<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitLossReportResource extends JsonResource
{
    public function toArray($request): array
    {
        $quantity = (float) $this->sold_quantity;
        $unitCost = (float) $this->unit_cost;
        $totalCost = (float) $this->cost_total;
        $unitSellingPrice = (float) $this->selling_unit_price;
        $salesTotal = (float) $this->sales_total;
        $grossProfit = (float) $this->gross_profit;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category_name' => $this->category?->name,
            'sold_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'cost_total' => $totalCost,
            'selling_unit_price' => $unitSellingPrice,
            'sales_total' => $salesTotal,
            'gross_profit' => $grossProfit,
            'unit_cost_currency' => AppLibrary::currencyAmountFormat($unitCost),
            'cost_total_currency' => AppLibrary::currencyAmountFormat($totalCost),
            'selling_unit_price_currency' => AppLibrary::currencyAmountFormat($unitSellingPrice),
            'sales_total_currency' => AppLibrary::currencyAmountFormat($salesTotal),
            'gross_profit_currency' => AppLibrary::currencyAmountFormat($grossProfit),
        ];
    }
}
