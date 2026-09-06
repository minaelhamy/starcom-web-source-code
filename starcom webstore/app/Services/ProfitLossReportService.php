<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProfitLossReportService
{
    public function list(Request $request)
    {
        $query = $this->query($request)->with('category');
        $orderColumn = $request->get('order_column', 'products.name');
        $orderType = $request->get('order_type', 'asc');

        $allowedOrderColumns = [
            'products.name',
            'sold_quantity',
            'unit_cost',
            'cost_total',
            'selling_unit_price',
            'sales_total',
            'gross_profit',
        ];

        if (!in_array($orderColumn, $allowedOrderColumns, true)) {
            $orderColumn = 'products.name';
        }

        $query->orderBy($orderColumn, $orderType === 'desc' ? 'desc' : 'asc');

        return (int) $request->get('paginate', 1) === 1
            ? $query->paginate(max(1, (int) $request->get('per_page', 10)))
            : $query->get();
    }

    public function summary(Request $request): array
    {
        $rows = $this->query($request)->get();

        return [
            'total_products' => $rows->count(),
            'total_quantity' => (float) $rows->sum('sold_quantity'),
            'total_cost' => (float) $rows->sum('cost_total'),
            'total_sales' => (float) $rows->sum('sales_total'),
            'gross_profit' => (float) $rows->sum('gross_profit'),
        ];
    }

    private function query(Request $request): Builder
    {
        $query = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.product_category_id',
                'products.buying_price',
            ])
            ->selectRaw('SUM(ABS(stocks.quantity)) AS sold_quantity')
            ->selectRaw('products.buying_price AS unit_cost')
            ->selectRaw('SUM(ABS(stocks.quantity) * COALESCE(products.buying_price, 0)) AS cost_total')
            ->selectRaw('SUM(stocks.total) / NULLIF(SUM(ABS(stocks.quantity)), 0) AS selling_unit_price')
            ->selectRaw('SUM(stocks.total) AS sales_total')
            ->selectRaw('SUM(stocks.total) - SUM(ABS(stocks.quantity) * COALESCE(products.buying_price, 0)) AS gross_profit')
            ->join('stocks', function ($join) {
                $join->on('stocks.product_id', '=', 'products.id')
                    ->where('stocks.model_type', '=', Order::class);
            })
            ->join('orders', 'orders.id', '=', 'stocks.model_id')
            ->groupBy([
                'products.id',
                'products.name',
                'products.sku',
                'products.product_category_id',
                'products.buying_price',
            ]);

        if ($request->filled('from_date')) {
            $query->where('orders.order_datetime', '>=', Carbon::parse($request->get('from_date'))->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('orders.order_datetime', '<=', Carbon::parse($request->get('to_date'))->endOfDay());
        }

        if ($request->filled('name')) {
            $query->where('products.name', 'like', '%' . $request->get('name') . '%');
        }

        if ($request->filled('product_category_id')) {
            $query->where('products.product_category_id', (int) $request->get('product_category_id'));
        }

        return $query;
    }
}
