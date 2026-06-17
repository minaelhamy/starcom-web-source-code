<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ReturnOrderRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\CreditFacilityOrderAllocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ReturnOrder;
use App\Models\Stock;
use App\Models\StockTax;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnOrderService
{
    public object $returnOrder;

    protected array $returnOrderFilter = [
        'user_id',
        'date',
        'reference_no',
        'order_serial_no',
        'total',
        'reason',
        'except'
    ];

    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return ReturnOrder::with(['user', 'order'])->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (!in_array($key, $this->returnOrderFilter, true)) {
                        continue;
                    }

                    if ($key === 'except') {
                        foreach (explode('|', $request) as $explode) {
                            $query->where('id', '!=', $explode);
                        }
                        continue;
                    }

                    if ($key === 'user_id') {
                        $query->where($key, $request);
                        continue;
                    }

                    if ($key === 'date' && !empty($request)) {
                        $dateStart = date('Y-m-d 00:00:00', strtotime($request));
                        $dateEnd   = date('Y-m-d 23:59:59', strtotime($request));
                        $query->where($key, '>=', $dateStart)->where($key, '<=', $dateEnd);
                        continue;
                    }

                    $query->where($key, 'like', '%' . $request . '%');
                }
            })->orderBy($orderColumn, $orderType)->$method($methodValue);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function store(ReturnOrderRequest $request): object
    {
        try {
            DB::transaction(function () use ($request) {
                $order = $this->loadReturnableOrder((int) $request->order_id, true);
                $products = $this->normalizedProducts($request->products);

                $this->returnOrder = ReturnOrder::create([
                    'user_id'        => $order->user_id,
                    'order_id'       => $order->id,
                    'date'           => date('Y-m-d H:i:s', strtotime($request->date)),
                    'reference_no'   => $request->reference_no,
                    'order_serial_no'=> $order->order_serial_no,
                    'subtotal'       => 0,
                    'tax'            => 0,
                    'discount'       => 0,
                    'total'          => 0,
                    'refund_amount'  => 0,
                    'refund_meta'    => null,
                    'reason'         => $request->reason ?: '',
                ]);

                $this->applyReturn($this->returnOrder, $order, $products);

                if ($request->file) {
                    $this->returnOrder->addMediaFromRequest('file')->toMediaCollection('returnOrder');
                }
            });

            return $this->returnOrder;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function show(ReturnOrder $returnOrder): ReturnOrder
    {
        try {
            return $returnOrder->load(['media', 'order', 'stocks.stockTaxes', 'user']);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function edit(ReturnOrder $returnOrder): ReturnOrder
    {
        try {
            return $returnOrder->load(['order', 'stocks.stockTaxes', 'user']);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function update(ReturnOrderRequest $request, ReturnOrder $returnOrder): object
    {
        try {
            DB::transaction(function () use ($request, $returnOrder) {
                $returnOrder = ReturnOrder::with(['stocks.stockTaxes', 'order'])->lockForUpdate()->findOrFail($returnOrder->id);

                $this->reverseReturn($returnOrder, false);

                $order = $this->loadReturnableOrder((int) $request->order_id, true);
                $products = $this->normalizedProducts($request->products);

                $returnOrder->update([
                    'user_id'         => $order->user_id,
                    'order_id'        => $order->id,
                    'date'            => date('Y-m-d H:i:s', strtotime($request->date)),
                    'reference_no'    => $request->reference_no,
                    'order_serial_no' => $order->order_serial_no,
                    'subtotal'        => 0,
                    'tax'             => 0,
                    'discount'        => 0,
                    'total'           => 0,
                    'refund_amount'   => 0,
                    'refund_meta'     => null,
                    'reason'          => $request->reason ?: '',
                ]);

                $this->applyReturn($returnOrder, $order, $products);

                if ($request->file) {
                    $file = $returnOrder->getFirstMedia('returnOrder');
                    if ($file) {
                        $file->delete();
                    }

                    $returnOrder->addMediaFromRequest('file')->toMediaCollection('returnOrder');
                }

                $this->returnOrder = $returnOrder->fresh(['order', 'stocks.stockTaxes', 'user']);
            });

            return $this->returnOrder;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function destroy(ReturnOrder $returnOrder): void
    {
        try {
            DB::transaction(function () use ($returnOrder) {
                $returnOrder = ReturnOrder::with(['stocks.stockTaxes', 'order'])->lockForUpdate()->findOrFail($returnOrder->id);
                $this->reverseReturn($returnOrder, true);
                $returnOrder->delete();
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function invoiceLookup(string $invoiceNumber, ?ReturnOrder $returnOrder = null): Order
    {
        $order = Order::with(['user', 'paymentMethod', 'orderProducts.stockTaxes', 'orderProducts.product'])
            ->where('order_serial_no', $invoiceNumber)
            ->firstOrFail();

        $this->assertReturnableOrder($order);

        if ($returnOrder) {
            $existingReturnedProducts = $returnOrder->stocks->keyBy(fn ($stock) => $stock->item_type . ':' . $stock->item_id);
            foreach ($order->orderProducts as $orderProduct) {
                $key = $orderProduct->item_type . ':' . $orderProduct->item_id;
                $existingReturn = $existingReturnedProducts->get($key);
                if ($existingReturn) {
                    $orderProduct->quantity = -round(abs((float) $orderProduct->quantity) + abs((float) $existingReturn->quantity), 2);
                }
            }
        }

        return $order;
    }

    public function downloadAttachment(ReturnOrder $returnOrder)
    {
        $file = $returnOrder->getFirstMedia('returnOrder');
        if (!$file) {
            throw new Exception(trans('all.message.attachment_not_found'), 422);
        }

        return response()->download($file->getPath(), $file->file_name);
    }

    protected function applyReturn(ReturnOrder $returnOrder, Order $order, Collection $products): void
    {
        $order->loadMissing(['paymentMethod', 'orderProducts.stockTaxes', 'orderProducts.product']);
        $this->assertReturnableOrder($order);

        $originalTotal = round((float) $order->total, 6);
        $refundMeta = $this->buildRefundMetaDefaults($order);

        $returnSubtotal = 0.0;
        $returnTax = 0.0;
        $returnDiscount = 0.0;
        $returnTotal = 0.0;

        $stocksById = $order->orderProducts->keyBy('id');
        if ($products->isEmpty()) {
            throw new Exception(trans('all.message.product_invalid'), 422);
        }

        foreach ($products as $product) {
            /** @var Stock|null $orderStock */
            $orderStock = $stocksById->get((int) $product['order_stock_id']);
            if (!$orderStock) {
                throw new Exception('تعذر العثور على أحد أصناف الفاتورة المختارة.', 422);
            }

            $currentQuantity = round(abs((float) $orderStock->quantity), 2);
            $returnQuantity = round((float) $product['quantity'], 2);
            if ($returnQuantity <= 0 || $returnQuantity - $currentQuantity > 0.00001) {
                throw new Exception('كمية المرتجع أكبر من الكمية الحالية في الفاتورة.', 422);
            }

            $currentDiscount = round((float) $orderStock->discount, 6);
            $currentTax = round((float) $orderStock->tax, 6);
            $currentSubtotal = round((float) $orderStock->subtotal, 6);
            $currentTotal = round((float) $orderStock->total, 6);

            $discountPerUnit = $currentQuantity > 0 ? $currentDiscount / $currentQuantity : 0;
            $taxPerUnit = $currentQuantity > 0 ? $currentTax / $currentQuantity : 0;
            $subtotalPerUnit = $currentQuantity > 0 ? $currentSubtotal / $currentQuantity : 0;
            $totalPerUnit = $currentQuantity > 0 ? $currentTotal / $currentQuantity : 0;

            $returnDiscountLine = round($discountPerUnit * $returnQuantity, 6);
            $returnTaxLine = round($taxPerUnit * $returnQuantity, 6);
            $returnSubtotalLine = round($subtotalPerUnit * $returnQuantity, 6);
            $returnTotalLine = round($totalPerUnit * $returnQuantity, 6);

            $returnStock = Stock::create([
                'model_type'      => ReturnOrder::class,
                'model_id'        => $returnOrder->id,
                'item_type'       => $orderStock->item_type,
                'item_id'         => $orderStock->item_id,
                'variation_names' => $orderStock->variation_names,
                'product_id'      => $orderStock->product_id,
                'price'           => $orderStock->price,
                'quantity'        => $returnQuantity,
                'discount'        => $returnDiscountLine,
                'tax'             => $returnTaxLine,
                'subtotal'        => $returnSubtotalLine,
                'total'           => $returnTotalLine,
                'sku'             => $orderStock->sku,
                'status'          => Status::ACTIVE,
            ]);

            foreach ($orderStock->stockTaxes as $stockTax) {
                $taxAmount = $currentQuantity > 0
                    ? round(((float) $stockTax->tax_amount / $currentQuantity) * $returnQuantity, 6)
                    : 0;

                StockTax::create([
                    'stock_id'   => $returnStock->id,
                    'product_id' => $returnStock->product_id,
                    'tax_id'     => $stockTax->tax_id,
                    'name'       => $stockTax->name,
                    'code'       => $stockTax->code,
                    'tax_rate'   => $stockTax->tax_rate,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $remainingQuantity = round($currentQuantity - $returnQuantity, 2);
            if ($remainingQuantity <= 0.00001) {
                StockTax::where('stock_id', $orderStock->id)->delete();
                $orderStock->delete();
            } else {
                $orderStock->quantity = -$remainingQuantity;
                $orderStock->discount = round($discountPerUnit * $remainingQuantity, 6);
                $orderStock->tax = round($taxPerUnit * $remainingQuantity, 6);
                $orderStock->subtotal = round($subtotalPerUnit * $remainingQuantity, 6);
                $orderStock->total = round($totalPerUnit * $remainingQuantity, 6);
                $orderStock->save();

                foreach ($orderStock->stockTaxes as $stockTax) {
                    $stockTax->tax_amount = $currentQuantity > 0
                        ? round(((float) $stockTax->tax_amount / $currentQuantity) * $remainingQuantity, 6)
                        : 0;
                    $stockTax->save();
                }
            }

            $returnSubtotal += $returnSubtotalLine;
            $returnTax += $returnTaxLine;
            $returnDiscount += $returnDiscountLine;
            $returnTotal += $returnTotalLine;
        }

        $this->recalculateOrderTotals($order);

        $refundAmount = round(max(0, $originalTotal - (float) $order->total), 6);
        $refundMeta = $this->processRefundForReturn($order, $refundAmount, $refundMeta, $returnOrder);

        $returnOrder->subtotal = round($returnSubtotal, 6);
        $returnOrder->tax = round($returnTax, 6);
        $returnOrder->discount = round($returnDiscount, 6);
        $returnOrder->total = round($returnTotal, 6);
        $returnOrder->refund_amount = $refundAmount;
        $returnOrder->refund_meta = $refundMeta;
        $returnOrder->save();
    }

    protected function reverseReturn(ReturnOrder $returnOrder, bool $deleteAttachment): void
    {
        $order = Order::with(['orderProducts.stockTaxes'])->lockForUpdate()->findOrFail($returnOrder->order_id);

        if ($returnOrder->refund_meta) {
            app(WalletService::class)->reverseReturnRefund(
                $order,
                $returnOrder->refund_meta,
                'Reverse return refund for invoice #' . $order->order_serial_no,
                ['return_order_id' => $returnOrder->id]
            );
        }

        foreach ($returnOrder->stocks()->with('stockTaxes')->get() as $returnStock) {
            $orderStock = Stock::where([
                'model_type' => Order::class,
                'model_id'   => $order->id,
                'item_type'  => $returnStock->item_type,
                'item_id'    => $returnStock->item_id,
                'status'     => $returnStock->status,
            ])->first();

            if (!$orderStock) {
                $orderStock = Stock::create([
                    'product_id'      => $returnStock->product_id,
                    'model_type'      => Order::class,
                    'model_id'        => $order->id,
                    'item_type'       => $returnStock->item_type,
                    'item_id'         => $returnStock->item_id,
                    'variation_names' => $returnStock->variation_names,
                    'price'           => $returnStock->price,
                    'quantity'        => -(float) $returnStock->quantity,
                    'discount'        => $returnStock->discount,
                    'tax'             => $returnStock->tax,
                    'sku'             => $returnStock->sku,
                    'status'          => (int) $order->active === 1 ? Status::ACTIVE : Status::INACTIVE,
                    'subtotal'        => $returnStock->subtotal,
                    'total'           => $returnStock->total,
                ]);
            } else {
                $orderStock->quantity = round((float) $orderStock->quantity - (float) $returnStock->quantity, 2);
                $orderStock->discount = round((float) $orderStock->discount + (float) $returnStock->discount, 6);
                $orderStock->tax = round((float) $orderStock->tax + (float) $returnStock->tax, 6);
                $orderStock->subtotal = round((float) $orderStock->subtotal + (float) $returnStock->subtotal, 6);
                $orderStock->total = round((float) $orderStock->total + (float) $returnStock->total, 6);
                $orderStock->save();
            }

            foreach ($returnStock->stockTaxes as $returnStockTax) {
                $existingTax = StockTax::where('stock_id', $orderStock->id)
                    ->where('tax_id', $returnStockTax->tax_id)
                    ->first();

                if ($existingTax) {
                    $existingTax->tax_amount = round((float) $existingTax->tax_amount + (float) $returnStockTax->tax_amount, 6);
                    $existingTax->save();
                } else {
                    StockTax::create([
                        'stock_id'   => $orderStock->id,
                        'product_id' => $orderStock->product_id,
                        'tax_id'     => $returnStockTax->tax_id,
                        'name'       => $returnStockTax->name,
                        'code'       => $returnStockTax->code,
                        'tax_rate'   => $returnStockTax->tax_rate,
                        'tax_amount' => $returnStockTax->tax_amount,
                    ]);
                }
            }
        }

        $stockIds = $returnOrder->stocks->pluck('id');
        if ($stockIds->isNotEmpty()) {
            StockTax::whereIn('stock_id', $stockIds)->delete();
        }
        $returnOrder->stocks()->delete();

        $this->recalculateOrderTotals($order);

        $refundMeta = $returnOrder->refund_meta ?? [];
        $order->wallet_paid_amount = (float) ($refundMeta['previous_wallet_paid_amount'] ?? $order->wallet_paid_amount);
        $order->cash_on_delivery_amount = (float) ($refundMeta['previous_cash_on_delivery_amount'] ?? $order->cash_on_delivery_amount);
        $order->payment_status = (int) ($refundMeta['previous_payment_status'] ?? $order->payment_status);
        $order->save();

        if ($deleteAttachment) {
            $file = $returnOrder->getFirstMedia('returnOrder');
            if ($file) {
                $file->delete();
            }
        }
    }

    protected function buildRefundMetaDefaults(Order $order): array
    {
        return [
            'previous_total'                   => round((float) $order->total, 6),
            'previous_payment_status'          => (int) $order->payment_status,
            'previous_wallet_paid_amount'      => round((float) $order->wallet_paid_amount, 6),
            'previous_cash_on_delivery_amount' => round((float) $order->cash_on_delivery_amount, 6),
            'facility_refunds'                 => [],
            'direct_wallet_refund_amount'      => 0,
            'cash_wallet_refund_amount'        => 0,
        ];
    }

    protected function processRefundForReturn(Order $order, float $refundAmount, array $refundMeta, ReturnOrder $returnOrder): array
    {
        $currentFacilityAllocated = round((float) CreditFacilityOrderAllocation::where('order_id', $order->id)->sum('amount'), 6);

        $currentWalletPaidAmount = round((float) $order->wallet_paid_amount, 6);
        $currentDirectWalletPaid = max(0, round($currentWalletPaidAmount - $currentFacilityAllocated, 6));
        $isFullyPaid = (int) $refundMeta['previous_payment_status'] === PaymentStatus::PAID;

        $maxCashPaid = $isFullyPaid
            ? max(0, round((float) ($refundMeta['previous_total'] ?? 0) - (float) ($refundMeta['previous_wallet_paid_amount'] ?? 0), 6))
            : 0;

        $facilityRefundAmount = min($refundAmount, $currentFacilityAllocated);
        $remainingRefund = round($refundAmount - $facilityRefundAmount, 6);
        $directWalletRefundAmount = min($remainingRefund, $currentDirectWalletPaid);
        $remainingRefund = round($remainingRefund - $directWalletRefundAmount, 6);
        $cashRefundAmount = min($remainingRefund, $maxCashPaid);

        if ($facilityRefundAmount > 0 || $directWalletRefundAmount > 0 || $cashRefundAmount > 0) {
            $walletRefundMeta = app(WalletService::class)->refundOrderAmounts(
                $order,
                $facilityRefundAmount,
                $directWalletRefundAmount,
                $cashRefundAmount,
                'Refund for return order #' . $returnOrder->id . ' / invoice #' . $order->order_serial_no,
                ['return_order_id' => $returnOrder->id]
            );

            $refundMeta['facility_refunds'] = $walletRefundMeta['facility_refunds'];
            $refundMeta['direct_wallet_refund_amount'] = $walletRefundMeta['direct_wallet_refund_amount'];
            $refundMeta['cash_wallet_refund_amount'] = $walletRefundMeta['cash_wallet_refund_amount'];
        }

        $refundedWalletAmount = round(
            collect($refundMeta['facility_refunds'])->sum('amount') + (float) $refundMeta['direct_wallet_refund_amount'],
            6
        );

        $order->wallet_paid_amount = max(0, round((float) $refundMeta['previous_wallet_paid_amount'] - $refundedWalletAmount, 6));

        if ($isFullyPaid) {
            $order->cash_on_delivery_amount = max(0, round((float) $order->total - (float) $order->wallet_paid_amount, 6));
            $order->payment_status = PaymentStatus::PAID;
        } else {
            $order->cash_on_delivery_amount = max(0, round((float) $order->total - (float) $order->wallet_paid_amount, 6));
            $order->payment_status = $order->cash_on_delivery_amount > 0 ? PaymentStatus::UNPAID : PaymentStatus::PAID;
        }

        $order->save();

        return $refundMeta;
    }

    protected function recalculateOrderTotals(Order $order): void
    {
        $order->subtotal = round((float) $order->orderProducts()->sum('subtotal'), 6);
        $order->tax = round((float) $order->orderProducts()->sum('tax'), 6);
        $order->discount = round((float) $order->orderProducts()->sum('discount'), 6);
        $order->total = round((float) $order->orderProducts()->sum('total'), 6);
        $order->save();
    }

    protected function loadReturnableOrder(int $orderId, bool $lock = false): Order
    {
        $query = Order::with(['user', 'paymentMethod', 'orderProducts.stockTaxes', 'orderProducts.product'])
            ->whereKey($orderId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $order = $query->firstOrFail();
        $this->assertReturnableOrder($order);

        return $order;
    }

    protected function assertReturnableOrder(Order $order): void
    {
        if (in_array((int) $order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
            throw new Exception('لا يمكن إنشاء مرتجع لفاتورة ملغية أو مرفوضة.', 422);
        }

        if ($order->orderProducts->isEmpty()) {
            throw new Exception('الفاتورة المختارة لا تحتوي على أصناف متاحة للمرتجع.', 422);
        }
    }

    protected function normalizedProducts(string $products): Collection
    {
        $decoded = collect(json_decode($products, true) ?? [])
            ->map(function ($product) {
                $product['quantity'] = round((float) ($product['quantity'] ?? 0), 2);
                return $product;
            })
            ->filter(fn ($product) => (float) ($product['quantity'] ?? 0) > 0)
            ->values();

        if ($decoded->isEmpty()) {
            throw new Exception('يجب إدخال كمية مرتجع لصنف واحد على الأقل.', 422);
        }

        return $decoded;
    }
}
