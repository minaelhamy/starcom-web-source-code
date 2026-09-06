<?php

namespace App\Exports;

use App\Http\Requests\PaginateRequest;
use App\Services\ProfitLossReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitLossReportExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly ProfitLossReportService $profitLossReportService,
        private readonly PaginateRequest $request,
    ) {
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $request = clone $this->request;
        $request->merge(['paginate' => 0]);

        return $this->profitLossReportService->list($request)
            ->map(fn ($row) => [
                $row->name,
                $row->category?->name,
                abs((float) $row->sold_quantity),
                (float) $row->unit_cost,
                (float) $row->cost_total,
                (float) $row->selling_unit_price,
                (float) $row->sales_total,
                (float) $row->gross_profit,
            ]);
    }

    public function headings(): array
    {
        return [
            'Product',
            'Category',
            'Quantity Sold',
            'Unit Cost',
            'Total Cost',
            'Average Selling Price',
            'Sales Total',
            'Gross Profit',
        ];
    }
}
