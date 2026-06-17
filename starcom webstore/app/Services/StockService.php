<?php

namespace App\Services;

use App\Enums\Ask;
use Exception;
use App\Models\Stock;
use App\Enums\Status;
use App\Models\ProductVariation;
use App\Libraries\AppLibrary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;
use Illuminate\Pagination\LengthAwarePaginator;

class StockService
{
    public $items = [];
    public $links;
    public array $totals = [
        'purchase_value' => 0,
        'sales_value'    => 0,
    ];
    protected $stockFilter = [
        'product_name',
        'status',
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $this->items = [];
            $this->totals = [
                'purchase_value' => 0,
                'sales_value'    => 0,
            ];

            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            $stocks =  Stock::with('product', 'item')->where('status', Status::ACTIVE)->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->stockFilter)) {
                        if ($key == "product_name") {
                            $query->whereHas('product', function ($query) use ($request) {
                                $query->where('name', 'like', '%' . $request . '%');
                            })->get();
                        } else {
                            $query->where($key, 'like', '%' . $request . '%');
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->get();

            if (!blank($stocks)) {
                $stocks->groupBy('product_id')?->map(function ($product) {
                    $product->groupBy('item_id')?->map(function ($item) {
                        $firstStock = $item->first();
                        $productModel = $firstStock['product'];
                        $stockQuantity = max(0, round((float) $item->sum('quantity'), 2));
                        $buyingPrice = (float) ($productModel['buying_price'] ?? 0);
                        $sellingPrice = $this->resolveSellingPrice($firstStock);
                        $purchaseValue = $stockQuantity * $buyingPrice;
                        $salesValue = $stockQuantity * $sellingPrice;

                        $this->totals['purchase_value'] += $purchaseValue;
                        $this->totals['sales_value'] += $salesValue;

                        $this->items[] = [
                            'product_id'               => $firstStock['product_id'],
                            'product_name'             => $productModel['name'],
                            'variation_names'          => $firstStock['variation_names'],
                            'status'                   => $productModel['status'],
                            'stock'                    => $productModel['can_purchasable'] === Ask::NO ? "N/C" : $stockQuantity,
                            'purchase_value'           => $purchaseValue,
                            'purchase_value_currency'  => AppLibrary::currencyAmountFormat($purchaseValue),
                            'sales_value'              => $salesValue,
                            'sales_value_currency'     => AppLibrary::currencyAmountFormat($salesValue),
                        ];
                    });
                });
            } else {
                $this->items = [];
            }

            if ($method == 'paginate') {
                return $this->paginate($this->items, $methodValue, null, URL::to('/') . '/api/admin/stock');
            }

            return $this->items;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function formattedTotals(): array
    {
        return [
            'purchase_value'          => $this->totals['purchase_value'],
            'purchase_value_currency' => AppLibrary::currencyAmountFormat($this->totals['purchase_value']),
            'sales_value'             => $this->totals['sales_value'],
            'sales_value_currency'    => AppLibrary::currencyAmountFormat($this->totals['sales_value']),
        ];
    }

    private function resolveSellingPrice(Stock $stock): float
    {
        if ($stock->item_type === ProductVariation::class && $stock->item) {
            return (float) ($stock->item->price ?? 0);
        }

        return (float) ($stock->product?->selling_price ?? 0);
    }

    public function paginate(
        $items,
        $perPage = 15,
        $page = null,
        $baseUrl = null,
        $options = []
    ) {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);

        $items = $items instanceof Collection ?
            $items : Collection::make($items);

        $lap = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );

        if ($baseUrl) {
            $lap->setPath($baseUrl);
        }

        return $lap;
    }
}
