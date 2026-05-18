<?php

namespace App\Exports;

use App\Http\Requests\PaginateRequest;
use App\Libraries\AppLibrary;
use App\Services\StockService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockExport implements FromCollection, WithHeadings
{

    public StockService $stockService;
    public PaginateRequest $request;

    public function __construct(StockService $stockService, $request)
    {
        $this->stockService = $stockService;
        $this->request         = $request;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $stockArray = [];
        $stocksArray = $this->stockService->list($this->request);
        foreach ($stocksArray as $stock) {
            $stockArray[] = [
                strlen($stock['variation_names']) > 0 ?  $stock['product_name'] . '(' . $stock['variation_names'] . ')' : $stock['product_name'],
                $stock['stock'],
                $stock['purchase_value_currency'],
                $stock['sales_value_currency'],
                trans('statuse.' . $stock['status'])
            ];
        }
        return collect($stockArray);
    }
    public function headings(): array
    {
        return [
            trans('all.label.product_name'),
            trans('all.label.stock'),
            trans('all.label.purchase_value'),
            trans('all.label.sales_value'),
            trans('all.label.status'),
        ];
    }
}
