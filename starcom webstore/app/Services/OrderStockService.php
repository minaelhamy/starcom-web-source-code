<?php

namespace App\Services;

use App\Enums\Ask;
use App\Enums\Status;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Stock;
use Exception;

class OrderStockService
{
    public function assertProductsAvailable(iterable $products, bool $lockStockRows = false): void
    {
        $aggregatedItems = [];

        foreach ($products as $product) {
            $quantity = (int) ($product->quantity ?? 0);
            if ($quantity < 1) {
                throw new Exception('الكمية المطلوبة غير صحيحة.');
            }

            $productId = (int) ($product->product_id ?? 0);
            $variationId = (int) ($product->variation_id ?? 0);
            $productModel = Product::query()->select('id', 'name', 'status', 'can_purchasable', 'maximum_purchase_quantity')
                ->find($productId);

            if (!$productModel || (int) $productModel->status !== Status::ACTIVE) {
                throw new Exception('أحد المنتجات المطلوبة غير متاح حالياً.');
            }

            $itemType = Product::class;
            $itemId = $productModel->id;

            if ($variationId > 0) {
                $variation = ProductVariation::query()->select('id', 'product_id')
                    ->where('product_id', $productId)
                    ->find($variationId);

                if (!$variation) {
                    throw new Exception('أحد خيارات المنتج المطلوبة غير متاح حالياً.');
                }

                $itemType = ProductVariation::class;
                $itemId = $variation->id;
            }

            $key = $itemType . ':' . $itemId;
            if (!isset($aggregatedItems[$key])) {
                $aggregatedItems[$key] = [
                    'product_name' => $productModel->name,
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'requested_quantity' => 0,
                    'can_purchasable' => (int) $productModel->can_purchasable,
                    'maximum_purchase_quantity' => (int) $productModel->maximum_purchase_quantity,
                ];
            }

            $aggregatedItems[$key]['requested_quantity'] += $quantity;
        }

        $this->assertAggregatedItemsAvailable($aggregatedItems, $lockStockRows);
    }

    public function assertOrderCanBeActivated(Order $order, bool $lockStockRows = false): void
    {
        $aggregatedItems = [];

        foreach ($order->orderProducts()->where('status', Status::INACTIVE)->get() as $stock) {
            $quantity = abs((int) $stock->quantity);
            if ($quantity < 1) {
                continue;
            }

            $key = $stock->item_type . ':' . $stock->item_id;
            if (!isset($aggregatedItems[$key])) {
                $aggregatedItems[$key] = [
                    'product_name' => $stock->product?->name ?: 'المنتج',
                    'item_type' => $stock->item_type,
                    'item_id' => (int) $stock->item_id,
                    'requested_quantity' => 0,
                    'can_purchasable' => (int) ($stock->product?->can_purchasable ?? Ask::YES),
                    'maximum_purchase_quantity' => (int) ($stock->product?->maximum_purchase_quantity ?? 0),
                ];
            }

            $aggregatedItems[$key]['requested_quantity'] += $quantity;
        }

        $this->assertAggregatedItemsAvailable($aggregatedItems, $lockStockRows);
    }

    public function activateOrderStocks(Order $order): void
    {
        $inactiveOrderStocks = Stock::where([
            'model_id' => $order->id,
            'model_type' => Order::class,
            'status' => Status::INACTIVE,
        ]);

        if (!$inactiveOrderStocks->exists()) {
            return;
        }

        $this->assertOrderCanBeActivated($order, true);

        $inactiveOrderStocks->update(['status' => Status::ACTIVE]);
    }

    public function releaseOrderStocks(Order $order): void
    {
        Stock::where([
            'model_id' => $order->id,
            'model_type' => Order::class,
            'status' => Status::ACTIVE,
        ])->update(['status' => Status::INACTIVE]);
    }

    protected function assertAggregatedItemsAvailable(array $aggregatedItems, bool $lockStockRows): void
    {
        foreach ($aggregatedItems as $item) {
            $requestedQuantity = (int) $item['requested_quantity'];
            $maximumPurchaseQuantity = (int) $item['maximum_purchase_quantity'];

            if ($maximumPurchaseQuantity > 0 && $requestedQuantity > $maximumPurchaseQuantity) {
                throw new Exception('الكمية المطلوبة من ' . $item['product_name'] . ' تتجاوز الحد الأقصى المسموح به.');
            }

            $availableQuantity = $item['can_purchasable'] === Ask::NO
                ? (int) env('NON_PURCHASE_QUANTITY', 999999)
                : $this->availableQuantityForItem($item['item_type'], (int) $item['item_id'], $lockStockRows);

            if ($requestedQuantity > $availableQuantity) {
                throw new Exception('الكمية المطلوبة من ' . $item['product_name'] . ' غير متاحة في المخزون. المتاح حالياً: ' . max(0, $availableQuantity) . '.');
            }
        }
    }

    protected function availableQuantityForItem(string $itemType, int $itemId, bool $lockStockRows): int
    {
        $query = Stock::query()
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->where('status', Status::ACTIVE);

        if ($lockStockRows) {
            $query->lockForUpdate();
        }

        return max(0, (int) $query->sum('quantity'));
    }
}
